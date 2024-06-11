<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Logger;
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
    public const FULL_REINDEX_TO_REALTIME_JOBS_RATIO = 0.33;
    public const UNLOCK_STACKED_JOBS_AFTER_MINUTES = 15;
    public const CLEAR_ARCHIVE_LOGS_AFTER_DAYS = 30;

    public const SUCCESS_LOG = 'algoliasearch_queue_log.txt';
    public const ERROR_LOG = 'algoliasearch_queue_errors.log';

    public const FAILED_JOB_ARCHIVE_CRITERIA = 'retries >= max_retries';
    public const MOVE_INDEX_METHOD_NAME = 'moveIndexWithSetSettings';

    /** @var AdapterInterface */
    protected $db;

    /** @var string */
    protected $table;

    /** @var string */
    protected $logTable;

    /** @var string */
    protected $archiveTable;

    /** @var ObjectManagerInterface */
    protected $objectManager;

    /** @var ConsoleOutput */
    protected $output;

    /** @var int */
    protected $elementsPerPage;

    /** @var ConfigHelper */
    protected $configHelper;

    /** @var Logger */
    protected $logger;

    protected $jobCollectionFactory;

    /** @var int */
    protected $maxSingleJobDataSize;

    /** @var int */
    protected $noOfFailedJobs = 0;

    /** @var array */
    protected $staticJobMethods = [
        'saveConfigurationToAlgolia',
        'moveIndexWithSetSettings',
        'deleteObjects',
    ];

    /** @var array */
    protected $logRecord;

    /**
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param JobCollectionFactory $jobCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param ObjectManagerInterface $objectManager
     * @param ConsoleOutput $output
     */
    public function __construct(
        ConfigHelper $configHelper,
        Logger $logger,
        JobCollectionFactory $jobCollectionFactory,
        ResourceConnection $resourceConnection,
        ObjectManagerInterface $objectManager,
        ConsoleOutput $output
    ) {
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->jobCollectionFactory = $jobCollectionFactory;

        $this->table = $resourceConnection->getTableName('algoliasearch_queue');
        $this->logTable = $resourceConnection->getTableName('algoliasearch_queue_log');
        $this->archiveTable = $resourceConnection->getTableName('algoliasearch_queue_archive');

        //$this->db = $resourceConnection->getConnection();

        $this->objectManager = $objectManager;
        $this->db = $objectManager->create(ResourceConnection::class)->getConnection('core_write');
        $this->output = $output;

        $this->elementsPerPage = $this->configHelper->getNumberOfElementByPage();

        $this->maxSingleJobDataSize = $this->configHelper->getNumberOfElementByPage();
    }

    /**
     * @param string $className
     * @param string $method
     * @param array $data
     * @param int $dataSize
     * @param bool $isFullReindex
     */
    public function addToQueue($className, $method, array $data, $dataSize = 1, $isFullReindex = false)
    {
        if (is_object($className)) {
            $className = get_class($className);
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
                'debug' => $this->configHelper->isEnhancedQueueArchiveEnabled() ? (new \Exception)->getTraceAsString() : null
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
     * @return float|null
     */
    public function getAverageProcessingTime()
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
     * @param int|null $nbJobs
     * @param bool $force
     *
     * @throws Exception
     */
    public function runCron($nbJobs = null, $force = false)
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

        $this->run($nbJobs);

        $this->logRecord['duration'] = time() - $started;

        if (php_sapi_name() === 'cli') {
            $this->output->writeln(
                $this->logRecord['processed_jobs'] . ' jobs processed in ' . $this->logRecord['duration'] . ' seconds.'
            );
        }

        $this->db->insert($this->logTable, $this->logRecord);
    }

    /**
     * Returns a more portable where clause as a string (useful across multiple db calls that do not always accept an array)
     * e.g. alternative to something like...
     * ['job_id IN (?)' => $job->getMergedIds()]
     *
     * @param Job $job
     * @return string
     */
    protected function jobToWhereClause(Job $job): string {
        return sprintf('job_id IN (%s)',implode(',', $job->getMergedIds()));
    }

    /**
     * @param Job $job
     * @return void
     * @throws \Exception
     */
    protected function processJob(Job $job): void {
        $job->execute();

        $where = $this->jobToWhereClause($job);

        if ($this->configHelper->isEnhancedQueueArchiveEnabled()) {
            $this->archiveSuccessfulJobs($where);
        }

        // Delete one by one
        $this->db->delete($this->table, $where);

        $this->logRecord['processed_jobs'] += count($job->getMergedIds());
    }

    /**
     * @param Job $job
     * @param Exception $e
     * @return void
     */
    protected function handleFailedJob(Job $job, Exception $e): void {
        $this->noOfFailedJobs++;

        // Log error information
        $logMessage = 'Queue processing ' . $job->getPid() . ' [KO]:
                    Class: ' . $job->getClass() . ',
                    Method: ' . $job->getMethod() . ',
                    Parameters: ' . json_encode($job->getDecodedData());
        $this->logger->log($logMessage);

        $logMessage = date('c') . ' ERROR: ' . get_class($e) . ':
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

        // Do not nullify PID until archived (want to preserve for debugging to identify potential multi thread interlacing)
        $this->db->update($this->table, [
            'pid' => null,
        ], $where);

        if (php_sapi_name() === 'cli') {
            $this->output->writeln($logMessage);
        }
    }

    /**
     * @param int $maxJobs
     *
     * @throws Exception
     */
    public function run($maxJobs)
    {
        $this->clearOldFailingJobs();

        $jobs = $this->getJobs($maxJobs);

        if ($jobs === []) {
            return;
        }

        // Run all reserved jobs
        foreach ($jobs as $job) {
            // If there are some failed jobs before move, we want to skip the move
            // as most probably not all products have prices reindexed
            // and therefore are not indexed yet in TMP index
            // TODO: Refactor this
            if ($job->getMethod() === self::MOVE_INDEX_METHOD_NAME && $this->noOfFailedJobs > 0) {
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
            $this->run(-1);
        }
    }

    /**
     * Archive jobs based on desired columns and where clause filter criteria
     *
     * @param array $sourceColumns
     * @param array $targetColumns
     * @param string $whereClause
     * @return void
     */
    protected function archiveJobs(array $sourceColumns, array $targetColumns, string $whereClause): void {
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
     * @see clearOldFailingJobs
     * @return void
     */
    protected function archiveFailedJobs(string $whereClause = self::FAILED_JOB_ARCHIVE_CRITERIA) : void
    {
        $sourceColumns =['pid', 'class', 'method', 'data', 'retries', 'error_log', 'data_size', 'created', 'NOW()', 'is_full_reindex', 'debug'];
        $targetColumns = ['pid', 'class', 'method', 'data', 'retries', 'error_log', 'data_size', 'created_at', 'processed_at', 'is_full_reindex', 'debug'];
        $this->archiveJobs(
            $sourceColumns,
            $targetColumns,
            $whereClause);
    }

    /**
     * Archive a successful job - based on supplied where clause criteria
     *
     * @param string $whereClause
     * @return void
     */
    protected function archiveSuccessfulJobs(string $whereClause): void {
        $sourceColumns =['pid', 'class', 'method', 'data', 'retries', 'CONVERT(\'\', CHAR)', 'data_size', 'created', 'NOW()', 'is_full_reindex', 'CONVERT(1,UNSIGNED)', 'debug'];
        $targetColumns = ['pid', 'class', 'method', 'data', 'retries', 'error_log', 'data_size', 'created_at', 'processed_at', 'is_full_reindex', 'success', 'debug'];
        $this->archiveJobs(
            $sourceColumns,
            $targetColumns,
            $whereClause);
    }

    /**
     * @param int $maxJobs
     *
     * @throws Exception
     *
     * @return Job[]
     *
     */
    protected function getJobs($maxJobs)
    {
        $maxJobs = ($maxJobs === -1) ? $this->configHelper->getNumberOfJobToRun() : $maxJobs;

        $fullReindexJobsLimit = (int) ceil(self::FULL_REINDEX_TO_REALTIME_JOBS_RATIO * $maxJobs);

        try {
            $this->db->beginTransaction();

            $fullReindexJobs = $this->fetchJobs($fullReindexJobsLimit, true);
            $fullReindexJobsCount = count($fullReindexJobs);

            $realtimeJobsLimit = (int) $maxJobs - $fullReindexJobsCount;

            $realtimeJobs = $this->fetchJobs($realtimeJobsLimit, false);

            $jobs = array_merge($fullReindexJobs, $realtimeJobs);
            $jobsCount = count($jobs);

            if ($jobsCount > 0 && $jobsCount < $maxJobs) {
                $restLimit = $maxJobs - $jobsCount;
                $lastFullReindexJobId = max($this->getJobsIdsFromMergedJobs($jobs));

                $restFullReindexJobs = $this->fetchJobs($restLimit, true, $lastFullReindexJobId);

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
     * @param int $jobsLimit
     * @param bool $fetchFullReindexJobs
     * @param int|null $lastJobId
     *
     * @return Job[]
     */
    protected function fetchJobs($jobsLimit, $fetchFullReindexJobs = false, $lastJobId = null)
    {
        $jobs = [];

        $actualBatchSize = 0;
        $maxBatchSize = $this->configHelper->getNumberOfElementByPage() * $jobsLimit;

        $limit = $maxJobs = $jobsLimit;
        $offset = 0;

        $fetchFullReindexJobs = $fetchFullReindexJobs ? 1 : 0;

        while ($actualBatchSize < $maxBatchSize) {
            $jobsCollection = $this->jobCollectionFactory->create();
            $jobsCollection
                ->addFieldToFilter('pid', ['null' => true])
                ->addFieldToFilter('is_full_reindex', $fetchFullReindexJobs)
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
            $limit = max(0, $maxJobs - $rawJobsCount);

            // $jobs will always be completely set from $rawJobs
            // Without resetting not-merged jobs would be stacked
            $jobs = [];

            if (count($rawJobs) === $maxJobs) {
                $jobs = $rawJobs;

                break;
            }

            foreach ($rawJobs as $job) {
                $jobSize = (int) $job->getDataSize();

                if ($actualBatchSize + $jobSize <= $maxBatchSize || !$jobs) {
                    $jobs[] = $job;
                    $actualBatchSize += $jobSize;
                } else {
                    break 2;
                }
            }
        }

        return $jobs;
    }

    /**
     * @param Job[] $unmergedJobs
     *
     * @return Job[]
     */
    protected function mergeJobs(array $unmergedJobs)
    {
        $unmergedJobs = $this->sortJobs($unmergedJobs);

        $jobs = [];

        /** @var Job $currentJob */
        $currentJob = array_shift($unmergedJobs);
        $nextJob = null;

        while ($currentJob !== null) {
            if (count($unmergedJobs) > 0) {
                $nextJob = array_shift($unmergedJobs);

                if ($currentJob->canMerge($nextJob, $this->maxSingleJobDataSize)) {
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
    protected function sortJobs(array $jobs)
    {
        $sortedJobs = [];

        $tempSortableJobs = [];

        /** @var Job $job */
        foreach ($jobs as $job) {
            $job->prepare();

            if (in_array($job->getMethod(), $this->staticJobMethods, true)) {
                $sortedJobs = $this->stackSortedJobs($sortedJobs, $tempSortableJobs, $job);
                $tempSortableJobs = [];

                continue;
            }

            $tempSortableJobs[] = $job;
        }

        $sortedJobs = $this->stackSortedJobs($sortedJobs, $tempSortableJobs);

        return $sortedJobs;
    }

    /**
     * @param Job[] $sortedJobs
     * @param Job[] $tempSortableJobs
     * @param Job|null $job
     *
     * @return array
     */
    protected function stackSortedJobs(array $sortedJobs, array $tempSortableJobs, Job $job = null)
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

    /**
     * @return array
     */
    protected function jobSort()
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
    protected function lockJobs(array $jobs)
    {
        $jobsIds = $this->getJobsIdsFromMergedJobs($jobs);

        if ($jobsIds !== []) {
            $pid = getmypid();
            $this->db->update($this->table, [
                'locked_at' => date('Y-m-d H:i:s'),
                'pid' => $pid,
            ], ['job_id IN (?)' => $jobsIds]);
        }

        // Persist to local objects for later reference and to address bugs where referenced data in object is not present
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
    protected function getJobsIdsFromMergedJobs(array $mergedJobs)
    {
        $jobsIds = [];
        foreach ($mergedJobs as $job) {
            $jobsIds = array_merge($jobsIds, $job->getMergedIds());
        }

        return $jobsIds;
    }

    /**
     * @return void
     */
    protected function clearOldFailingJobs()
    {
        // Enhanced archive will have already logged this failure
        if (!$this->configHelper->isEnhancedQueueArchiveEnabled()) {
            $this->archiveFailedJobs();
        }
        // DEBUG:
        // $this->archiveJobs('1 = 1');
        $this->db->delete($this->table, self::FAILED_JOB_ARCHIVE_CRITERIA);
    }

    /**
     * @throws Zend_Db_Statement_Exception
     */
    protected function clearOldLogRecords()
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

    /**
     * @return void
     */
    protected function clearOldArchiveRecords()
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

    /**
     * @return void
     */
    protected function unlockStackedJobs()
    {
        $this->db->update($this->table, [
            'locked_at' => null,
            'pid' => null,
        ], ['locked_at < (NOW() - INTERVAL ' . self::UNLOCK_STACKED_JOBS_AFTER_MINUTES . ' MINUTE)']);
    }

    /**
     * @return bool
     */
    protected function shouldEmptyQueue()
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
