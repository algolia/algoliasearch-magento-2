<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel\Query;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Algolia\AlgoliaSearch\Model\Query::class,
            \Algolia\AlgoliaSearch\Model\ResourceModel\Query::class
        );
    }
}
