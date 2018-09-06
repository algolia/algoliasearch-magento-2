<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\Context;

class Job extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    protected function _construct()
    {
        $this->_init('algoliasearch_queue', 'job_id');
    }
}
