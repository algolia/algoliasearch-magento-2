<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel\CategoryVersion;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{

    /**
     * @var string
     */
    protected $_idFieldName = 'version_id';

    protected function _construct()
    {
        $this->_init(\Algolia\AlgoliaSearch\Model\CategoryVersion::class, \Algolia\AlgoliaSearch\Model\ResourceModel\CategoryVersion::class);
    }

}
