<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel\Run;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';

    protected $_eventPrefix = 'algoliasearch_queue_run_collection';

    protected $_eventObject = 'run_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            'Algolia\AlgoliaSearch\Model\Run',
            'Algolia\AlgoliaSearch\Model\ResourceModel\Run'
        );
    }
}
