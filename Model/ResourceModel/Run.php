<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel;

use Algolia\AlgoliaSearch\Api\Data\RunInterface;

class Run extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init(RunInterface::TABLE_NAME, RunInterface::FIELD_RUN_ID);
    }
}
