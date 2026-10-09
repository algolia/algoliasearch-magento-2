<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Api\Data\JobInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job\Collection;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job\CollectionFactory as JobCollectionFactory;
use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\ObjectManagerInterface;
use PDO;
use Symfony\Component\Console\Output\ConsoleOutput;
use Zend_Db_Expr;
use Zend_Db_Statement_Exception;

class Queue
{
    public const float FULL_REINDEX_TO_REALTIME_JOBS_RATIO = 0.33;
    public const int UNLOCK_STACKED_JOBS_AFTER_MINUTES = 15;
    public const int CLEAR_ARCHIVE_LOGS_AFTER_DAYS = 30;

    public const string FAILED_JOB_ARCHIVE_CRITERIA = 'retries >= max_retries';
    public const string MOVE_INDEX_METHOD_NAME = 'moveIndexWithSetSettings';

    protected AdapterInterface $db;

    protected string $table;

    protected string $logTable;

    protected string $archiveTable;

    /**
     * Failure bucket for jobs without a store (NULL store_id, legacy rows).
     * Deliberately shared with store_id 0 (admin scope), which never carries indexing jobs.
     */
    protected const int NO_STORE = 0;

    /** @var array<int, int> Failed job counts, keyed by store ID (jobs without a store land in NO_STORE) */
    protected array $noOfFailedJobsByStore = [];

    /** @var string[] */
    protected array $staticJobMethods = [
        'saveConfigurationToAlgolia',
        'moveIndexWithSetSettings',
        'deleteObjects',
    ];

    /** @var array<string, mixed> */
    protected array $logRecord;

    protected array $storeMaxBatchSizes;

    public function __construct(
        protected ConfigHelper           $configHelper,
        protected DiagnosticsLogger      $logger,
        protected JobCollectionFactory   $jobCollectionFactory,
        protected ResourceConnection     $resourceConnection,
        protected ObjectManagerInterface $objectManager,
        protected ConsoleOutput          $output
    ) {
        $this->table = $resourceConnection->getTableName('algoliasearch_queue');
        $this->logTable = $resourceConnection->getTableName('algoliasearch_queue_log');
        $this->archiveTable = $resourceConnection->getTableName('algoliasearch_queue_archive');
        $this->db = $objectManager->create(ResourceConnection::class)->getConnection('core_write');
    }

    /**
     * @throws AlgoliaException
     */
    public function addToQueue(
        string $className,
        string $method,
        array $data,
        int $dataSize = 1,
        bool $isFullReindex = false
    ): void {
        if (!isset(Job::ALLOWED_HANDLERS[$className]) ||
            !in_array($method, Job::ALLOWED_HANDLERS[$className], true)) {
            throw new AlgoliaException('Unauthorized job handler');
        }

        if ($this->configHelper->isQueueActive()) {
            $this->db->insert($this->table, [
                'created'   => date('Y-m-d H:i:s'),
                'class'     => $className,
                'method'    => $method,
                'data'      => json_encode($data),
                'data_size' => $dataSize,
                'pid'       => null,
                'max_retries' => $this->configHelper->getRetryLimit(),
                'is_full_reindex' => $isFullReindex ? 1 : 0,
                'debug' => $this->configHelper->isEnhancedQueueArchiveEnabled()
                    ? (new Exception)->getTraceAsString()
                    : null,
                'store_id' => isset($data['storeId']) ? (int) $data['storeId'] : null,
            ]);
        } else {
            $object = $this->objectManager->get($className);

            call_user_func_array([$object, $method], $data);
        }
    }

    /**
     * Return the average processing time for the 2 last two days
     * (null if there was less than 100 runs with processed jobs)
     *
     * @throws Zend_Db_Statement_Exception
     *
     */
    public function getAverageProcessingTime(): ?float
    {
        $select = $this->db->select()
            ->from($this->logTable, ['number_of_runs' => 'COUNT(duration)', 'average_time' => 'AVG(duration)'])
            ->where('processed_jobs > 0 AND with_empty_queue = 0 AND started >= (CURDATE() - INTERVAL 2 DAY)');

        $data = $this->db->query($select)->fetch();

        return (int) $data['number_of_runs'] >= 100 && isset($data['average_time']) ?
            (float) $data['average_time'] :
            null;
    }

    /**
     *
     * @throws Exception
     */
    public function runCron(?int $nbJobs = null, bool $force = false, ?int $storeId = null): void
    {
        if (!$this->configHelper->isQueueActive() && $force === false) {
            return;
        }

        $this->clearOldLogRecords();
        $this->clearOldArchiveRecords();
        $this->unlockStackedJobs();

        $this->logRecord = [
            'started' => date('Y-m-d H:i:s'),
            'processed_jobs' => 0,
            'with_empty_queue' => 0,
        ];

        $started = time();

        if ($nbJobs === null) {
            $nbJobs = $this->configHelper->getNumberOfJobToRun();
            if ($this->shouldEmptyQueue() === true) {
                $nbJobs = -1;

                $this->logRecord['with_empty_queue'] = 1;
            }
        }

        $this->run($nbJobs, $storeId);

        $this->logRecord['duration'] = time() - $started;

        if (php_sapi_name() === 'cli') {
            $this->output->writeln(
                $this->logRecord['processed_jobs'] . ' jobs processed in '
                    . $this->logRecord['duration'] . ' seconds.'
            );
        }

        $this->db->insert($this->logTable, $this->logRecord);
    }

    /**
     * Returns a more portable where clause as a string
     * (useful across multiple db calls that do not always accept an array)
     * e.g. alternative to something like...
     * ['job_id IN (?)' => $job->getMergedIds()]
     *
     */
    protected function jobToWhereClause(Job $job): string
    {
        return sprintf('job_id IN (%s)', implode(',', $job->getMergedIds()));
    }

    /**
     * @throws Exception
     */
    protected function processJob(Job $job): void
    {
        $job->execute();

        $where = $this->jobToWhereClause($job);

        if ($this->configHelper->isEnhancedQueueArchiveEnabled()) {
            $this->archiveSuccessfulJobs($where);
        }

        // Delete one by one
        $this->db->delete($this->table, $where);

        $this->logRecord['processed_jobs'] += count($job->getMergedIds());
    }

    protected function getFailureStoreKey(Job $job): int
    {
        return (int) ($job->getStoreId() ?? self::NO_STORE);
    }

    protected function handleFailedJob(Job $job, Exception $e): void
    {
        $storeKey = $this->getFailureStoreKey($job);
        $this->noOfFailedJobsByStore[$storeKey] = ($this->noOfFailedJobsByStore[$storeKey] ?? 0) + 1;

        // Log error information
        $logMessage = 'Queue processing ' . $job->getPid() . ' [KO]:
                    Class: ' . $job->getClass() . ',
                    Method: ' . $job->getMethod() . ',
                    Parameters: ' . json_encode($job->getDecodedData());
        $this->logger->log($logMessage);

        $logMessage = date('c') . ' ERROR: ' . $e::class . ':
                    ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() .
            "\nStack trace:\n" . $e->getTraceAsString();
        $this->logger->log($logMessage);

        $where = $this->jobToWhereClause($job);

        $this->db->update($this->table, [
            'retries' => new Zend_Db_Expr('retries + 1'),
            'error_log' => $logMessage,
        ], $where);

        if ($this->configHelper->isEnhancedQueueArchiveEnabled()) {
            // Record *every* instance of a failed job in context of successful jobs for debugging
            $this->archiveFailedJobs($where);
        }

        // Do not nullify PID until archived
        // (want to preserve for debugging to identify potential multi thread interlacing)
        $this->db->update($this->table, ['pid' => null,], $where);

        if (php_sapi_name() === 'cli') {
            $this->output->writeln($logMessage);
        }
    }

    /**
     *
     * @throws Exception
     */
    public function run(int $maxJobs, ?int $storeId = null): void
    {
        $this->clearOldFailingJobs($storeId);

        $jobs = $this->getJobs($maxJobs, $storeId);

        if ($jobs === []) {
            return;
        }

        // Run all reserved jobs
        foreach ($jobs as $job) {
            // If there are some failed jobs for this store before move, we want to skip the move
            // as most probably not all products have prices reindexed
            // and therefore are not indexed yet in TMP index
            // TODO: Refactor this
            if ($job->getMethod() === self::MOVE_INDEX_METHOD_NAME
                && ($this->noOfFailedJobsByStore[$this->getFailureStoreKey($job)] ?? 0) > 0
            ) {
                // Set pid to NULL so it's not deleted after
                $this->db->update($this->table, ['pid' => null], ['job_id = ?' => $job->getId()]);

                continue;
            }

            try {
                $this->processJob($job);
            } catch (Exception $e) {
                $this->handleFailedJob($job, $e);
            }
        }

        $isFullReindex = ($maxJobs === -1);
        if ($isFullReindex) {
            $this->run(-1, $storeId);
        }
    }

    /**
     * Archive jobs based on desired columns and where clause filter criteria
     *
     */
    protected function archiveJobs(array $sourceColumns, array $targetColumns, string $whereClause): void
    {
        $select = $this->db->select()
            ->from($this->table, $sourceColumns)
            ->where($whereClause);

        $query = $this->db->insertFromSelect(
            $select,
            $this->archiveTable,
            $targetColumns
        );

        $this->db->query($query);
    }

    /**
     * Archive failed jobs - should be same criteria as jobs deleted when performing cleanup
     *
     * @see clearOldFailingJobs
     */
    protected function archiveFailedJobs(string $whereClause = self::FAILED_JOB_ARCHIVE_CRITERIA) : void
    {
        $sourceColumns =[
            'pid', 'class', 'method', 'data', 'retries', 'error_log', 'data_size',
            'created', 'NOW()', 'is_full_reindex', 'debug',
        ];
        $targetColumns = [
            'pid', 'class', 'method', 'data', 'retries', 'error_log', 'data_size',
            'created_at', 'processed_at', 'is_full_reindex', 'debug']
        ;
        $this->archiveJobs(
            $sourceColumns,
            $targetColumns,
            $whereClause
        );
    }

    /**
     * Archive a successful job - based on supplied where clause criteria
     *
     */
    protected function archiveSuccessfulJobs(string $whereClause): void
    {
        $sourceColumns =[
            'pid', 'class', 'method', 'data', 'retries', 'CONVERT(\'\', CHAR)', 'data_size',
            'created', 'NOW()', 'is_full_reindex', 'CONVERT(1,UNSIGNED)', 'debug',
        ];
        $targetColumns = [
            'pid', 'class', 'method', 'data', 'retries', 'error_log', 'data_size',
            'created_at', 'processed_at', 'is_full_reindex', 'success', 'debug',
        ];
        $this->archiveJobs(
            $sourceColumns,
            $targetColumns,
            $whereClause
        );
    }

    /**
     *
     *
     * @throws Exception
     *
     * @return Job[]
     *
     */
    protected function getJobs(int $maxJobs, ?int $storeId = null): array
    {
        $maxJobs = ($maxJobs === -1) ? $this->configHelper->getNumberOfJobToRun() : $maxJobs;

        $fullReindexJobsLimit = (int) ceil(self::FULL_REINDEX_TO_REALTIME_JOBS_RATIO * $maxJobs);

        try {
            $this->db->beginTransaction();

            $fullReindexJobs = $this->fetchJobs($fullReindexJobsLimit, true, null, $storeId);
            $fullReindexJobsCount = count($fullReindexJobs);

            $realtimeJobsLimit = (int) $maxJobs - $fullReindexJobsCount;

            $realtimeJobs = $this->fetchJobs($realtimeJobsLimit, false, null, $storeId);

            $jobs = array_merge($fullReindexJobs, $realtimeJobs);
            $jobsCount = count($jobs);

            if ($jobsCount > 0 && $jobsCount < $maxJobs) {
                $restLimit = $maxJobs - $jobsCount;

                if ($fullReindexJobsCount > 0) {
                    $lastFullReindexJobId = max($this->getJobsIdsFromMergedJobs($fullReindexJobs));
                } else {
                    $lastFullReindexJobId = max($this->getJobsIdsFromMergedJobs($jobs));
                }

                $restFullReindexJobs = $this->fetchJobs($restLimit, true, $lastFullReindexJobId, $storeId);

                $jobs = array_merge($jobs, $restFullReindexJobs);
            }

            $this->lockJobs($jobs);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();

            throw $e;
        }

        return $jobs;
    }

    /**
     *
     * @return Job[]
     */
    protected function fetchJobs(
        int $jobsLimit,
        bool $fetchFullReindexJobs = false,
        ?int $lastJobId = null,
        ?int $storeId = null
    ): array {
        $jobs = [];

        $actualBatchSize = -1;
        $maxBatchSize = 0;
        $limit = $jobsLimit;
        $offset = 0;

        $fetchFullReindexJobs = $fetchFullReindexJobs ? 1 : 0;

        while ($actualBatchSize < $maxBatchSize) {
            $jobsCollection = $this->jobCollectionFactory->create();
            $jobsCollection
                ->addFieldToFilter('pid', ['null' => true])
                ->addFieldToFilter('is_full_reindex', $fetchFullReindexJobs);

            if ($storeId !== null) {
                $jobsCollection->addFieldToFilter(JobInterface::FIELD_STORE_ID, $storeId);
            }

            $jobsCollection
                ->setOrder('job_id', Collection::SORT_ORDER_ASC)
                ->getSelect()
                ->limit($limit, $offset)
                ->forUpdate();

            if ($lastJobId !== null) {
                $jobsCollection->addFieldToFilter('job_id', ['gt' => $lastJobId]);
            }

            $rawJobs = $jobsCollection->getItems();

            if ($rawJobs === []) {
                break;
            }

            $rawJobs = array_merge($jobs, $rawJobs);
            $rawJobs = $this->mergeJobs($rawJobs);

            $rawJobsCount = count($rawJobs);

            $offset += $limit;
            $limit = max(0, $jobsLimit - $rawJobsCount);

            // $jobs will always be completely set from $rawJobs
            // Without resetting not-merged jobs would be stacked
            $jobs = [];

            // At this point, if this condition is true, this means that no merge was possible in the last iteration
            if (count($rawJobs) === $jobsLimit) {
                $jobs = $rawJobs;

                break;
            }

            // Introduced an array of job sizes to determine the total batch size currently processed
            // (sum of all jobs contained in the run)
            // This will determine if we can continue to loop over the jobs
            $jobSizes = [];

            foreach ($rawJobs as $job) {
                $jobSize = $job->getDataSize();
                $jobSizes[$job->getId()] = $jobSize;
                $jobs[] = $job;
            }

            // Final calculation for the loop
            $actualBatchSize = array_sum($jobSizes);
            $maxBatchSize = $this->calculateMaxBatchSize($jobs);
        }

        return $jobs;
    }

    /**
     * @param Job[] $jobs
     */
    protected function calculateMaxBatchSize(array $jobs): int
    {
        $maxBatchSize = 0;

        foreach ($jobs as $job) {
            $maxBatchSize += $this->getStoreMaxBatchSize($job->getStoreId());
        }

        return round($maxBatchSize / count($jobs));
    }

    /**
     * Returns the maximum batch size for a given store ID.
     *
     * @param int|null $storeId Nullable for correctness as jobs can conceivably be created without a store ID
     *  (e.g. legacy/third party/off-contract rows) which would claim with the default page size
     *
     * @return int
     */
    protected function getStoreMaxBatchSize(?int $storeId = null): int
    {
        if ($storeId === null) {
            return $this->configHelper->getNumberOfElementByPage();
        }

        if (!isset($this->storeMaxBatchSizes[$storeId])) {
            try {
                $this->storeMaxBatchSizes[$storeId] = $this->configHelper->getNumberOfElementByPage($storeId);
            } catch (Exception) {
                // In case a job was created before a store deletion
                $this->storeMaxBatchSizes[$storeId] = $this->configHelper->getNumberOfElementByPage();
            }
        }

        return $this->storeMaxBatchSizes[$storeId];
    }

    /**
     * @param Job[] $unmergedJobs
     *
     * @return Job[]
     */
    protected function mergeJobs(array $unmergedJobs): array
    {
        $unmergedJobs = $this->sortJobs($unmergedJobs);

        $jobs = [];

        /** @var Job $currentJob */
        $currentJob = array_shift($unmergedJobs);
        $nextJob = null;

        while ($currentJob !== null) {
            if (count($unmergedJobs) > 0) {
                $nextJob = array_shift($unmergedJobs);

                if ($currentJob->canMerge($nextJob, $this->getStoreMaxBatchSize($currentJob->getStoreId()))) {
                    $currentJob->merge($nextJob);

                    continue;
                }
            } else {
                $nextJob = null;
            }

            $jobs[] = $currentJob;
            $currentJob = $nextJob;
        }

        return $jobs;
    }

    /**
     * Sorts the jobs and preserves the order of jobs with static methods defined in $this->staticJobMethods
     *
     * @param Job[] $jobs
     *
     * @return Job[]
     */
    protected function sortJobs(array $jobs): array
    {
        $sortedJobs = [];

        $tempSortableJobs = [];

        foreach ($jobs as $job) {
            $job->prepare();

            if (in_array($job->getMethod(), $this->staticJobMethods, true)) {
                $sortedJobs = $this->stackSortedJobs($sortedJobs, $tempSortableJobs, $job);
                $tempSortableJobs = [];

                continue;
            }

            $tempSortableJobs[] = $job;
        }

        return $this->stackSortedJobs($sortedJobs, $tempSortableJobs);
    }

    /**
     * @param Job[] $sortedJobs
     * @param Job[] $tempSortableJobs
     *
     */
    protected function stackSortedJobs(array $sortedJobs, array $tempSortableJobs, ?Job $job = null): array
    {
        if ($tempSortableJobs && $tempSortableJobs !== []) {
            $tempSortableJobs = $this->jobSort(
                $tempSortableJobs,
                'class',
                SORT_ASC,
                'method',
                SORT_ASC,
                'store_id',
                SORT_ASC,
                'job_id',
                SORT_ASC
            );
        }

        $sortedJobs = array_merge($sortedJobs, $tempSortableJobs);

        if ($job !== null) {
            $sortedJobs = array_merge($sortedJobs, [$job]);
        }

        return $sortedJobs;
    }

    protected function jobSort(): array
    {
        $args = func_get_args();

        $data = array_shift($args);

        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = [];

                /**
                 * @var int $key
                 * @var Job $row
                 */
                foreach ($data as $key => $row) {
                    $tmp[$key] = $row->getData($field);
                }

                $args[$n] = $tmp;
            }
        }

        $args[] = &$data;

        call_user_func_array('array_multisort', $args);

        return array_pop($args);
    }

    /**
     * @param Job[] $jobs
     */
    protected function lockJobs(array $jobs): void
    {
        $jobsIds = $this->getJobsIdsFromMergedJobs($jobs);

        if ($jobsIds !== []) {
            $pid = getmypid();
            $this->db->update($this->table, [
                'locked_at' => date('Y-m-d H:i:s'),
                'pid' => $pid,
            ], ['job_id IN (?)' => $jobsIds]);
        }

        // Persist to local objects for later reference and to address bugs
        // where referenced data in object is not present
        // Not modifying persistence logic atm
        // TODO: Implement repository pattern / service contracts for jobs
        foreach ($jobs as $job) {
            $job->setData('pid', getmypid());
            $job->setData('locked_at', date('Y-m-d H:i:s'));
        }
    }

    /**
     * @param Job[] $mergedJobs
     *
     * @return string[]
     */
    protected function getJobsIdsFromMergedJobs(array $mergedJobs): array
    {
        $jobsIds = [];
        foreach ($mergedJobs as $job) {
            $jobsIds = array_merge($jobsIds, $job->getMergedIds());
        }

        return $jobsIds;
    }

    protected function clearOldFailingJobs(?int $storeId = null): void
    {
        $criteria = self::FAILED_JOB_ARCHIVE_CRITERIA;

        if ($storeId !== null) {
            // Keep the archive/delete pair scoped so two workers cleaning up different stores
            // cannot both INSERT the same rows into the archive
            $criteria .= ' AND store_id = ' . $storeId;
        }

        // Enhanced archive will have already logged this failure
        if (!$this->configHelper->isEnhancedQueueArchiveEnabled()) {
            $this->archiveFailedJobs($criteria);
        }
        // DEBUG:
        // $this->archiveJobs('1 = 1');
        $this->db->delete($this->table, $criteria);
    }

    /**
     * Returns the store IDs that currently have claimable jobs, ascending.
     *
     * Excludes locked rows (pid set), rows that exhausted their retries and store-agnostic
     * rows (store_id IS NULL), which store-scoped workers never claim. The caller must run
     * unlockStackedJobs() first so stale locks do not hide pending work.
     *
     * @return int[]
     */
    public function getStoreIdsWithPendingJobs(): array
    {
        $select = $this->db->select()
            ->from($this->table, new Zend_Db_Expr('DISTINCT store_id'))
            ->where('pid IS NULL')
            ->where('retries < max_retries')
            ->where('store_id IS NOT NULL')
            ->order('store_id');

        return array_map('intval', $this->db->fetchCol($select));
    }

    /**
     * @throws Zend_Db_Statement_Exception
     */
    protected function clearOldLogRecords(): void
    {
        $select = $this->db->select()
            ->from($this->logTable, ['id'])
            ->order(['started DESC', 'id DESC'])
            ->limit(PHP_INT_MAX, 25000);

        $idsToDelete = $this->db->query($select)->fetchAll(PDO::FETCH_COLUMN, 0);

        if ($idsToDelete) {
            $this->db->delete($this->logTable, ['id IN (?)' => $idsToDelete]);
        }
    }

    protected function clearOldArchiveRecords(): void
    {
        $archiveLogClearLimit = $this->configHelper->getArchiveLogClearLimit();
        // Adding a fallback in case this configuration was not set in a consistent way
        if ($archiveLogClearLimit < 1) {
            $archiveLogClearLimit = self::CLEAR_ARCHIVE_LOGS_AFTER_DAYS;
        }

        $this->db->delete(
            $this->archiveTable,
            'created_at < (NOW() - INTERVAL ' . $archiveLogClearLimit . ' DAY)'
        );
    }

    protected function unlockStackedJobs(): void
    {
        $this->db->update($this->table, [
            'locked_at' => null,
            'pid' => null,
        ], ['locked_at < (NOW() - INTERVAL ' . self::UNLOCK_STACKED_JOBS_AFTER_MINUTES . ' MINUTE)']);
    }

    protected function shouldEmptyQueue(): bool
    {
        if (getenv('PROCESS_FULL_QUEUE') && getenv('PROCESS_FULL_QUEUE') === '1') {
            return true;
        }

        if (getenv('EMPTY_QUEUE') && getenv('EMPTY_QUEUE') === '1') {
            return true;
        }

        return false;
    }
}
