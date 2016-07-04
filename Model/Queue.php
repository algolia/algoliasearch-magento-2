<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Logger;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Module\ResourceInterface;
use Magento\Framework\ObjectManagerInterface;

class Queue
{
    const SUCCESS_LOG = 'algoliasearch_queue_log.txt';
    const ERROR_LOG = 'algoliasearch_queue_errors.log';

    private $db;
    private $table;
    private $objectManager;

    private $elementsPerPage;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var Logger */
    private $logger;

    public function __construct(ConfigHelper $configHelper, Logger $logger, ResourceConnection $resourceConnection, ObjectManagerInterface $objectManager)
    {
        $this->configHelper = $configHelper;
        $this->logger = $logger;

        $this->table = $resourceConnection->getTableName('algoliasearch_queue');
        $this->db = $resourceConnection->getConnection('core_write');

        $this->objectManager = $objectManager;

        $this->elementsPerPage = $this->configHelper->getNumberOfElementByPage();
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

    public function runCron()
    {
        if (!$this->configHelper->isQueueActive()) {
            return;
        }

        $nbJobs = $this->configHelper->getNumberOfJobToRun();

        if (getenv('EMPTY_QUEUE') && getenv('EMPTY_QUEUE') == '1') {
            $nbJobs = -1;
        }

        $this->run($nbJobs);
    }

    public function run($limit)
    {
        $full_reindex = ($limit === -1);
        $limit = $full_reindex ? 1 : $limit;

        $element_count = 0;
        $jobs = [];
        $offset = 0;
        $max_size = $this->configHelper->getNumberOfElementByPage() * $limit;

        while ($element_count < $max_size) {
            $data = $this->db->query($this->db->select()->from($this->table, '*')->where('pid IS NULL')
                                              ->order(['job_id'])->limit($limit, $limit * $offset));
            $data = $data->fetchAll();

            $offset++;

            if (count($data) <= 0) {
                break;
            }

            foreach ($data as $job) {
                $job_size = (int) $job['data_size'];

                if ($element_count + $job_size <= $max_size) {
                    $jobs[] = $job;
                    $element_count += $job_size;
                } else {
                    break 2;
                }
            }
        }

        if (count($jobs) <= 0) {
            return;
        }

        $first_id = $jobs[0]['job_id'];
        $last_id = $jobs[count($jobs) - 1]['job_id'];

        $pid = getmypid();

        // Reserve all new jobs since last run
        $this->db->query("UPDATE {$this->db->quoteIdentifier($this->table, true)} SET pid = ".$pid.' WHERE job_id >= '.$first_id." AND job_id <= $last_id");

        foreach ($jobs as &$job) {
            $job['data'] = json_decode($job['data'], true);
        }

        $jobs = $this->sortAndMergeJob($jobs);

        // Run all reserved jobs
        foreach ($jobs as $job) {
            try {
                $model = $this->objectManager->get($job['class']);

                $method = $job['method'];
                $data = $job['data'];

                call_user_func_array([$model, $method], $data);
            } catch (\Exception $e) {
                // Increment retries and log error information
                $this->logger->log("Queue processing {$job['pid']} [KO]: Mage::getSingleton({$job['class']})->{$job['method']}(".json_encode($job['data']).')');
                $this->logger->log(date('c').' ERROR: '.get_class($e).": '{$e->getMessage()}' in {$e->getFile()}:{$e->getLine()}\n"."Stack trace:\n".$e->getTraceAsString());
            }
        }

        // Delete only when finished to be able to debug the queue if needed
        $where = $this->db->quoteInto('pid = ?', $pid);
        $this->db->delete($this->table, $where);

        if ($full_reindex) {
            $this->run(-1);
        }
    }

    private function insert($class, $method, $data, $data_size)
    {
        $this->db->insert($this->table, [
            'class'     => $class,
            'method'    => $method,
            'data'      => json_encode($data),
            'data_size' => $data_size,
            'pid'       => null,
        ]);
    }

    private function sortAndMergeJob($old_jobs)
    {
        usort($old_jobs, function ($a, $b) {
            if (strcmp($a['class'], $b['class']) !== 0) {
                return strcmp($a['class'], $b['class']);
            }

            if ($a['data']['store_id'] !== $b['data']['store_id']) {
                return $a['data']['store_id'] > $b['data']['store_id'];
            }

            return $a['job_id'] - $b['job_id'];
        });

        $jobs = [];

        $current_job = array_shift($old_jobs);
        $next_job = null;

        while ($current_job !== null) {
            if (count($old_jobs) > 0) {
                $next_job = array_shift($old_jobs);

                if ($this->mergeable($current_job, $next_job)) {
                    if (isset($current_job['data']['product_ids'])) {
                        $current_job['data']['product_ids'] = array_merge($current_job['data']['product_ids'], $next_job['data']['product_ids']);
                    } else {
                        $current_job['data']['category_ids'] = array_merge($current_job['data']['category_ids'], $next_job['data']['category_ids']);
                    }

                    continue;
                }
            } else {
                $next_job = null;
            }

            if (isset($current_job['data']['product_ids'])) {
                $current_job['data']['product_ids'] = array_unique($current_job['data']['product_ids']);
            }

            if (isset($current_job['data']['category_ids'])) {
                $current_job['data']['category_ids'] = array_unique($current_job['data']['category_ids']);
            }

            $jobs[] = $current_job;
            $current_job = $next_job;
        }

        return $jobs;
    }

    private function mergeable($j1, $j2)
    {
        if ($j1['class'] !== $j2['class']) {
            return false;
        }

        if ($j1['method'] !== $j2['method']) {
            return false;
        }

        if ($j1['data']['store_id'] !== $j2['data']['store_id']) {
            return false;
        }

        if ((!isset($j1['data']['product_ids']) || count($j1['data']['product_ids']) <= 0) && (!isset($j1['data']['category_ids']) || count($j1['data']['category_ids']) < 0)) {
            return false;
        }

        if ((!isset($j2['data']['product_ids']) || count($j2['data']['product_ids']) <= 0) && (!isset($j2['data']['category_ids']) || count($j2['data']['category_ids']) < 0)) {
            return false;
        }

        if (isset($j1['data']['product_ids']) && count($j1['data']['product_ids']) + count($j2['data']['product_ids']) > $this->elementsPerPage) {
            return false;
        }

        if (isset($j1['data']['category_ids']) && count($j1['data']['category_ids']) + count($j2['data']['category_ids']) > $this->elementsPerPage) {
            return false;
        }

        return true;
    }
}
