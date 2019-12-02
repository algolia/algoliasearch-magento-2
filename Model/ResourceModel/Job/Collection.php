<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel\Job;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'job_id';

    protected $_eventPrefix = 'algoliasearch_queue_job_collection';

    protected $_eventObject = 'jpb_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Algolia\AlgoliaSearch\Model\Job::class,
            \Algolia\AlgoliaSearch\Model\ResourceModel\Job::class
        );
    }
}
