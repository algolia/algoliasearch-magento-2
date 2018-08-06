<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel;

use \Magento\Framework\Model\ResourceModel\Db\Context;

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

    /**
     * @param int[] $ids IDs to delete
     *
     * @return Job
     */
    public function deleteIds($ids)
    {
        $condition = $this->getConnection()->quoteInto($this->getIdFieldName() . ' IN (?)', (array) $ids);
        $this->getConnection()->delete($this->getMainTable(), $condition);

        return $this;
    }

    /**
     * @return Job
     */
    public function deleteAll()
    {
        $this->getConnection()->delete($this->getMainTable());

        return $this;
    }
}
