<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Logger;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job\Collection;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job\CollectionFactory as JobCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class Queue
{
    const FULL_REINDEX_TO_REALTIME_JOBS_RATIO = 0.33;

    const SUCCESS_LOG = 'algoliasearch_queue_log.txt';
    const ERROR_LOG = 'algoliasearch_queue_errors.log';

    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    private $db;

    /** @var string */
    private $table;

    /** @var string */
    private $logTable;

    /** @var string */
    private $archiveTable;

    /** @var ObjectManagerInterface */
    private $objectManager;

    /** @var ConsoleOutput */
    private $output;

    /** @var int */
    private $elementsPerPage;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var Logger */
    private $logger;

    private $jobCollectionFactory;

    /** @var int */
    private $maxSingleJobDataSize;

    /** @var int */
    private $noOfFailedJobs = 0;

    /** @var array */
    private $staticJobMethods = [
        'saveConfigurationToAlgolia',
        'moveIndex',
        'deleteObjects',
    ];

    /** @var array */
    private $logRecord;

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

        $this->db = $resourceConnection->getConnection('core_write');

        $this->objectManager = $objectManager;
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
                'is_full_reindex' => $isFullReindex ? 1 : 0,
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
     * @throws \Zend_Db_Statement_Exception
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
     * @throws \Exception
     */
    public function runCron($nbJobs = null, $force = false)
    {
        if (!$this->configHelper->isQueueActive() && $force === false) {
            return;
        }

        $this->clearOldLogRecords();

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
     * @param int $maxJobs
     *
     * @throws \Exception
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
            if ($job->getMethod() === 'moveIndex' && $this->noOfFailedJobs > 0) {
                // Set pid to NULL so it's not deleted after
                $this->db->update($this->table, ['pid' => null], ['job_id = ?' => $job->getId()]);

                continue;
            }

            try {
                $job->execute();

                // Delete one by one
                $this->db->delete($this->table, ['job_id IN (?)' => $job->getMergedIds()]);

                $this->logRecord['processed_jobs'] += count($job->getMergedIds());
            } catch (\Exception $e) {
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

                $this->db->update($this->table, [
                    'pid' => null,
                    'retries' => new \Zend_Db_Expr('retries + 1'),
                    'error_log' => $logMessage,
                ], ['job_id IN (?)' => $job->getMergedIds()]);

                if (php_sapi_name() === 'cli') {
                    $this->output->writeln($logMessage);
                }
            }
        }

        $isFullReindex = ($maxJobs === -1);
        if ($isFullReindex) {
            $this->run(-1);

            return;
        }
    }

    /**
     * @param string $whereClause
     */
    private function archiveFailedJobs($whereClause)
    {
        $select = $this->db->select()
           ->from($this->table, ['pid', 'class', 'method', 'data', 'error_log', 'data_size', 'NOW()'])
           ->where($whereClause);

        $query = $this->db->insertFromSelect(
            $select,
            $this->archiveTable,
            ['pid', 'class', 'method', 'data', 'error_log', 'data_size', 'created_at']
        );

        $this->db->query($query);
    }

    /**
     * @param int $maxJobs
     *
     * @throws \Exception
     *
     * @return Job[]
     *
     */
    private function getJobs($maxJobs)
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
        } catch (\Exception $e) {
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
    private function fetchJobs($jobsLimit, $fetchFullReindexJobs = false, $lastJobId = null)
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
    private function mergeJobs(array $unmergedJobs)
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
    private function sortJobs(array $jobs)
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
    private function stackSortedJobs(array $sortedJobs, array $tempSortableJobs, Job $job = null)
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
    private function jobSort()
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
    private function lockJobs(array $jobs)
    {
        $jobsIds = $this->getJobsIdsFromMergedJobs($jobs);

        if ($jobsIds !== []) {
            $pid = getmypid();
            $this->db->update($this->table, ['pid' => $pid], ['job_id IN (?)' => $jobsIds]);
        }
    }

    /**
     * @param Job[] $mergedJobs
     *
     * @return string[]
     */
    private function getJobsIdsFromMergedJobs(array $mergedJobs)
    {
        $jobsIds = [];
        foreach ($mergedJobs as $job) {
            $jobsIds = array_merge($jobsIds, $job->getMergedIds());
        }

        return $jobsIds;
    }

    private function clearOldFailingJobs()
    {
        $retryLimit = $this->configHelper->getRetryLimit();

        if ($retryLimit > 0) {
            $where = $this->db->quoteInto('retries >= ?', $retryLimit);
            $this->archiveFailedJobs($where);

            return;
        }

        $this->archiveFailedJobs('retries > max_retries');
        $this->db->delete($this->table, 'retries > max_retries');
    }

    /**
     * @throws \Zend_Db_Statement_Exception
     */
    private function clearOldLogRecords()
    {
        $select = $this->db->select()
           ->from($this->logTable, ['id'])
           ->order(['started DESC', 'id DESC'])
           ->limit(PHP_INT_MAX, 25000);

        $idsToDelete = $this->db->query($select)->fetchAll(\PDO::FETCH_COLUMN, 0);

        if ($idsToDelete) {
            $this->db->delete($this->logTable, ['id IN (?)' => $idsToDelete]);
        }
    }

    /**
     * @return bool
     */
    private function shouldEmptyQueue()
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
