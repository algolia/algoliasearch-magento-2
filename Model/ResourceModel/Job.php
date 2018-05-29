<?php
namespace Algolia\AlgoliaSearch\Model\ResourceModel;


class Job extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context
    )
    {
        parent::__construct($context);
    }

    protected function _construct()
    {
        $this->_init('algoliasearch_queue', 'job_id');
    }

}
