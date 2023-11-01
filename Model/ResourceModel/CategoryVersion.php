<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel;

class CategoryVersion extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    const TABLE_NAME = 'algoliasearch_category_version';
    const ID = 'version_id';

    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, self::ID);
    }
}
