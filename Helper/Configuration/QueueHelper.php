<?php

namespace Algolia\AlgoliaSearch\Helper\Configuration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class QueueHelper
{
    public const IS_ACTIVE = 'algoliasearch_queue/queue/active';
    public const USE_BUILT_IN_CRON = 'algoliasearch_queue/queue/use_built_in_cron';
    public const NUMBER_OF_JOB_TO_RUN = 'algoliasearch_queue/queue/number_of_job_to_run';
    public const RETRY_LIMIT = 'algoliasearch_queue/queue/number_of_retries';
    public const USE_TMP_INDEX = 'algoliasearch_queue/queue/use_tmp_index';

    public function __construct(
        protected ScopeConfigInterface $configInterface,
    ) {}

    /**
     * @param $storeId
     * @return bool
     */
    public function isQueueActive($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::IS_ACTIVE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function useBuiltInCron($storeId = null)
    {
        return $this->configInterface->isSetFlag(self::USE_BUILT_IN_CRON, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getNumberOfJobToRun($storeId = null)
    {
        $nbJobs = (int)$this->configInterface->getValue(self::NUMBER_OF_JOB_TO_RUN, ScopeInterface::SCOPE_STORE, $storeId);

        return max($nbJobs, 1);
    }

    /**
     * @param $storeId
     * @return int
     */
    public function getRetryLimit($storeId = null)
    {
        return (int)$this->configInterface->getValue(self::RETRY_LIMIT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function useTmpIndex($storeId = null)
    {
        return $this->isQueueActive($storeId) &&
            $this->configInterface->isSetFlag(self::USE_TMP_INDEX, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
