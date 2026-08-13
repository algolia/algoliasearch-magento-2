<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel\QueueArchive;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class Collection
 *
 * @package Algolia\AlgoliaSearch\Model\ResourceModel\QueueArchive
 */
class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'archive_id';

    /**
     * Initialize resource collection
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Algolia\AlgoliaSearch\Model\QueueArchive::class,
            \Algolia\AlgoliaSearch\Model\ResourceModel\QueueArchive::class
        );
    }
}
