<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Logger;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;

class Queue
{
    const SUCCESS_LOG = 'algoliasearch_queue_log.txt';
    const ERROR_LOG = 'algoliasearch_queue_errors.log';

    private $db;
    private $table;
    private $logTable;
    private $objectManager;

    private $elementsPerPage;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var Logger */
    private $logger;

    protected $maxSingleJobDataSize;

    private $noOfFailedJobs = 0;

    private $staticJobMethods = [
        'saveConfigurationToAlgolia',
        'moveIndex',
        'deleteObjects',
    ];

    private $logRecord;

    public function __construct(
        ConfigHelper $configHelper,
        Logger $logger,
        ResourceConnection $resourceConnection,
        ObjectManagerInterface $objectManager
    ) {
        $this->configHelper = $configHelper;
        $this->logger = $logger;

        $this->table = $resourceConnection->getTableName('algoliasearch_queue');
        $this->logTable = $resourceConnection->getTableName('algoliasearch_queue_log');

        $this->db = $resourceConnection->getConnection('core_write');

        $this->objectManager = $objectManager;

        $this->elementsPerPage = $this->configHelper->getNumberOfElementByPage();

        $this->maxSingleJobDataSize = $this->configHelper->getNumberOfElementByPage();
    }

    public function addToQueue($className, $method, $data, $data_size = 1)
    {
        if (is_object($className)) {
            $className = get_class($className);
        }

        if ($this->configHelper->isQueueActive()) {
            $this->insert($className, $method, $data, $data_size);
        } else {
            $object = $this->objectManager->get($className);
            call_user_func_array([$object, $method], $data);
        }
    }

    private function insert($class, $method, $data, $data_size)
    {
        $this->db->insert($this->table, [
            'created'   => date('Y-m-d H:i:s'),
            'class'     => $class,
            'method'    => $method,
            'data'      => json_encode($data),
            'data_size' => $data_size,
            'pid'       => null,
        ]);
    }

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
            if (getenv('EMPTY_QUEUE') && getenv('EMPTY_QUEUE') == '1') {
                $nbJobs = -1;

                $this->logRecord['with_empty_queue'] = 1;
            }
        }

        $this->run($nbJobs);

        $this->logRecord['duration'] = time() - $started;

        $this->db->insert($this->logTable, $this->logRecord);
    }

    public function run($maxJobs)
    {
        $pid = getmypid();

        $jobs = $this->getJobs($maxJobs, $pid);

        if (empty($jobs)) {
            return;
        }

        // Run all reserved jobs
        foreach ($jobs as $job) {
            // If there are some failed jobs before move, we want to skip the move
            // as most probably not all products have prices reindexed
            // and therefore are not indexed yet in TMP index
            if ($job['method'] === 'moveIndex' && $this->noOfFailedJobs > 0) {
                // Set pid to NULL so it's not deleted after
                $this->db->query("UPDATE {$this->table} SET pid = NULL WHERE job_id = ".$job['job_id']);

                continue;
            }

            try {
                $model = $this->objectManager->get($job['class']);

                $method = $job['method'];
                $data = $job['data'];

                call_user_func_array([$model, $method], $data);

                $this->logRecord['processed_jobs'] += count($job['merged_ids']);
            } catch (\Exception $e) {
                $this->noOfFailedJobs++;

                // Increment retries, set the job ID back to NULL
                $updateQuery = "UPDATE {$this->table} 
                    SET pid = NULL, retries = retries + 1 
                    WHERE job_id IN (".implode(', ', (array) $job['merged_ids']).")";
                $this->db->query($updateQuery);

                // Log error information
                $logMessage = 'Queue processing '.$job['pid'].' [KO]: 
                    Class: '.$job['class'].', 
                    Method: '.$job['method'].', 
                    Parameters: '.json_encode($job['data']);
                $this->logger->log($logMessage);

                $logMessage = date('c').' ERROR: '.get_class($e).': 
                    '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine().
                    "\nStack trace:\n".$e->getTraceAsString();
                $this->logger->log($logMessage);
            }
        }

        // Delete only when finished to be able to debug the queue if needed
        $where = $this->db->quoteInto('pid = ?', $pid);
        $this->db->delete($this->table, $where);

        $isFullReindex = ($maxJobs === -1);
        if ($isFullReindex) {
            $this->run(-1);

            return;
        }
    }

    private function getJobs($maxJobs, $pid)
    {
        // Clear jobs with crossed max retries count
        $retryLimit = $this->configHelper->getRetryLimit();
        if ($retryLimit > 0) {
            $where = $this->db->quoteInto('retries >= ?', $retryLimit);
            $this->db->delete($this->table, $where);
        } else {
            $this->db->delete($this->table, 'retries > max_retries');
        }

        $jobs = [];

        $limit = $maxJobs = ($maxJobs === -1) ? $this->configHelper->getNumberOfJobToRun() : $maxJobs;
        $offset = 0;

        $maxBatchSize = $this->configHelper->getNumberOfElementByPage() * $limit;
        $actualBatchSize = 0;

        try {
            $this->db->beginTransaction();

            while ($actualBatchSize < $maxBatchSize) {
                $data = $this->db->query($this->db->select()->from($this->table, '*')->where('pid IS NULL')
                                                  ->order(['job_id'])->limit($limit, $offset)
                                                  ->forUpdate());
                $rawJobs = $data->fetchAll();
                $rowsCount = count($rawJobs);

                if ($rowsCount <= 0) {
                    break;
                }

                // If $jobs is empty, it's the first run
                if (empty($jobs)) {
                    $firstJobId = $rawJobs[0]['job_id'];
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

                if (count($rawJobs) == $maxJobs) {
                    $jobs = $rawJobs;
                    break;
                }

                foreach ($rawJobs as $job) {
                    $jobSize = (int) $job['data_size'];

                    if ($actualBatchSize + $jobSize <= $maxBatchSize || empty($jobs)) {
                        $jobs[] = $job;
                        $actualBatchSize += $jobSize;
                    } else {
                        break 2;
                    }
                }
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();

            throw $e;
        }


        if (isset($firstJobId)) {
            $lastJobId = $this->maxValueInArray($jobs, 'job_id');

            // Reserve all new jobs since last run
            $this->db->query("UPDATE {$this->db->quoteIdentifier($this->table, true)} 
                SET pid = ".$pid.' 
                WHERE job_id >= '.$firstJobId." AND job_id <= $lastJobId");
        }

        return $jobs;
    }

    private function prepareJobs($jobs)
    {
        foreach ($jobs as &$job) {
            $job['data'] = json_decode($job['data'], true);
            $job['merged_ids'][] = $job['job_id'];
        }

        return $jobs;
    }

    protected function mergeJobs($oldJobs)
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

    private function sortJobs($oldJobs)
    {
        // Method sorts the jobs and preserves the order of jobs with static methods defined in $this->staticJobMethods

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

    private function stackSortedJobs($sortedJobs, $tempSortableJobs, $job = null)
    {
        if (!empty($tempSortableJobs)) {
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

    private function mergeable($j1, $j2)
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

    private function maxValueInArray($array, $keyToSearch)
    {
        $currentMax = null;

        foreach ($array as $arr) {
            foreach ($arr as $key => $value) {
                if ($key == $keyToSearch && ($value >= $currentMax)) {
                    $currentMax = $value;
                }
            }
        }

        return $currentMax;
    }

    private function clearOldLogRecords()
    {
        $idsToDelete = $this->db->query("SELECT id 
                                    FROM {$this->logTable} 
                                    ORDER BY started DESC, id DESC 
                                    LIMIT 25000, ".PHP_INT_MAX)
                                ->fetchAll(\PDO::FETCH_COLUMN, 0);

        if ($idsToDelete) {
            $this->db->query("DELETE FROM {$this->logTable} WHERE id IN (" . implode(", ", $idsToDelete) . ")");
        }
    }
}
