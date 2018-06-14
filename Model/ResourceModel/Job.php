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

    /**
     * delete a list of entities by the ids
     *
     * @param int[] $ids ids to delete
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
     * delete all entities
     *
     * @return Job
     */
    public function deleteAll()
    {
        $this->getConnection()->delete($this->getMainTable());

        return $this;
    }
}
