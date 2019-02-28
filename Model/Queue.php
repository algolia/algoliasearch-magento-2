<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Logger;
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
        ResourceConnection $resourceConnection,
        ObjectManagerInterface $objectManager,
        ConsoleOutput $output
    ) {
        $this->configHelper = $configHelper;
        $this->logger = $logger;

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
            if ($job['method'] === 'moveIndex' && $this->noOfFailedJobs > 0) {
                // Set pid to NULL so it's not deleted after
                $this->db->update($this->table, ['pid' => null], ['job_id = ?' => $job['job_id']]);

                continue;
            }

            try {
                $model = $this->objectManager->get($job['class']);

                $method = $job['method'];
                $data = $job['data'];

                call_user_func_array([$model, $method], $data);

                // Delete one by one
                $this->db->delete($this->table, ['job_id IN (?)' => $job['merged_ids']]);

                $this->logRecord['processed_jobs'] += count($job['merged_ids']);
            } catch (\Exception $e) {
                $this->noOfFailedJobs++;

                // Log error information
                $logMessage = 'Queue processing ' . $job['pid'] . ' [KO]: 
                    Class: ' . $job['class'] . ', 
                    Method: ' . $job['method'] . ', 
                    Parameters: ' . json_encode($job['data']);
                $this->logger->log($logMessage);

                $logMessage = date('c') . ' ERROR: ' . get_class($e) . ': 
                    ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() .
                    "\nStack trace:\n" . $e->getTraceAsString();
                $this->logger->log($logMessage);

                $this->db->update($this->table, [
                    'pid' => null,
                    'retries' => new \Zend_Db_Expr('retries + 1'),
                    'error_log' => $logMessage,
                ], ['job_id IN (?)' => $job['merged_ids']]);

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
     * @return array
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
     *
     * @throws \Zend_Db_Statement_Exception
     *
     * @return array
     *
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
            $where = 'pid IS NULL AND is_full_reindex = ' . $fetchFullReindexJobs;

            if ($lastJobId !== null) {
                $where .= ' AND job_id > ' . $lastJobId;
            }

            $select = $this->db->select()
               ->from($this->table, '*')
               ->where($where)
               ->order(['job_id'])
               ->limit($limit, $offset)
               ->forUpdate();

            $data = $this->db->query($select);
            $rawJobs = $data->fetchAll();
            $rowsCount = count($rawJobs);

            if ($rowsCount <= 0) {
                break;
            }

            $rawJobs = $this->prepareJobs($rawJobs);
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
                $jobSize = (int) $job['data_size'];

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
     * @param array $jobs
     *
     * @return array
     */
    private function prepareJobs(array $jobs)
    {
        foreach ($jobs as &$job) {
            $job['data'] = json_decode($job['data'], true);
            $job['merged_ids'][] = $job['job_id'];
        }

        return $jobs;
    }

    /**
     * @param array $oldJobs
     *
     * @return array
     */
    private function mergeJobs(array $oldJobs)
    {
        $oldJobs = $this->sortJobs($oldJobs);

        $jobs = [];

        $currentJob = array_shift($oldJobs);
        $nextJob = null;

        while ($currentJob !== null) {
            if (count($oldJobs) > 0) {
                $nextJob = array_shift($oldJobs);

                if ($this->mergeable($currentJob, $nextJob)) {
                    // Use the job_id of the the very last job to properly mark processed jobs
                    $currentJob['job_id'] = max((int) $currentJob['job_id'], (int) $nextJob['job_id']);

                    $currentJob['merged_ids'][] = $nextJob['job_id'];

                    if (isset($currentJob['data']['product_ids'])) {
                        $currentJob['data']['product_ids'] = array_merge(
                            $currentJob['data']['product_ids'],
                            $nextJob['data']['product_ids']
                        );

                        $currentJob['data_size'] = count($currentJob['data']['product_ids']);
                    } elseif (isset($currentJob['data']['category_ids'])) {
                        $currentJob['data']['category_ids'] = array_merge(
                            $currentJob['data']['category_ids'],
                            $nextJob['data']['category_ids']
                        );

                        $currentJob['data_size'] = count($currentJob['data']['category_ids']);
                    }

                    continue;
                }
            } else {
                $nextJob = null;
            }

            if (isset($currentJob['data']['product_ids'])) {
                $currentJob['data']['product_ids'] = array_unique($currentJob['data']['product_ids']);
            }

            if (isset($currentJob['data']['category_ids'])) {
                $currentJob['data']['category_ids'] = array_unique($currentJob['data']['category_ids']);
            }

            $jobs[] = $currentJob;
            $currentJob = $nextJob;
        }

        return $jobs;
    }

    /**
     * Sorts the jobs and preserves the order of jobs with static methods defined in $this->staticJobMethods
     *
     * @param array $oldJobs
     *
     * @return array
     */
    private function sortJobs(array $oldJobs)
    {
        $sortedJobs = [];

        $tempSortableJobs = [];
        foreach ($oldJobs as $job) {
            if (in_array($job['method'], $this->staticJobMethods, true)) {
                $sortedJobs = $this->stackSortedJobs($sortedJobs, $tempSortableJobs, $job);
                $tempSortableJobs = [];

                continue;
            }

            // This one is needed for proper sorting
            if (isset($job['data']['store_id'])) {
                $job['store_id'] = $job['data']['store_id'];
            }

            $tempSortableJobs[] = $job;
        }

        $sortedJobs = $this->stackSortedJobs($sortedJobs, $tempSortableJobs);

        return $sortedJobs;
    }

    /**
     * @param array $sortedJobs
     * @param array $tempSortableJobs
     * @param array|null $job
     *
     * @return array
     */
    private function stackSortedJobs(array $sortedJobs, array $tempSortableJobs, array $job = null)
    {
        if ($tempSortableJobs && $tempSortableJobs !== []) {
            $tempSortableJobs = $this->arrayMultisort(
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
     * @param array $j1
     * @param array $j2
     *
     * @return bool
     */
    private function mergeable(array $j1, array $j2)
    {
        if ($j1['class'] !== $j2['class']) {
            return false;
        }

        if ($j1['method'] !== $j2['method']) {
            return false;
        }

        if (isset($j1['data']['store_id'])
            && isset($j2['data']['store_id'])
            && $j1['data']['store_id'] !== $j2['data']['store_id']) {
            return false;
        }

        if ((!isset($j1['data']['product_ids']) || count($j1['data']['product_ids']) <= 0)
            && (!isset($j1['data']['category_ids']) || count($j1['data']['category_ids']) < 0)) {
            return false;
        }

        if ((!isset($j2['data']['product_ids']) || count($j2['data']['product_ids']) <= 0)
            && (!isset($j2['data']['category_ids']) || count($j2['data']['category_ids']) < 0)) {
            return false;
        }

        if (isset($j1['data']['product_ids'])
            && count($j1['data']['product_ids']) + count($j2['data']['product_ids']) > $this->maxSingleJobDataSize) {
            return false;
        }

        if (isset($j1['data']['category_ids'])
            && count($j1['data']['category_ids']) + count($j2['data']['category_ids']) > $this->maxSingleJobDataSize) {
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    private function arrayMultisort()
    {
        $args = func_get_args();

        $data = array_shift($args);

        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = [];

                foreach ($data as $key => $row) {
                    $tmp[$key] = $row[$field];
                }

                $args[$n] = $tmp;
            }
        }

        $args[] = &$data;

        call_user_func_array('array_multisort', $args);

        return array_pop($args);
    }

    /**
     * @param array $jobs
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
     * @param array $mergedJobs
     *
     * @return array
     */
    private function getJobsIdsFromMergedJobs(array $mergedJobs)
    {
        $jobsIds = [];
        foreach ($mergedJobs as $job) {
            $jobsIds = array_merge($jobsIds, $job['merged_ids']);
        }

        return $jobsIds;
    }

    private function clearOldFailingJobs()
    {
        $retryLimit = $this->configHelper->getRetryLimit();

        if ($retryLimit > 0) {
            $retryLimit = 0;
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
