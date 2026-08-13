<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class QueueArchive
 *
 * @package Algolia\AlgoliaSearch\Model\ResourceModel
 */
class QueueArchive extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('algoliasearch_queue_archive', 'archive_id');
    }
}
