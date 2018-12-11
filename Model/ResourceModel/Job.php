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

    /**
     * @throws \Zend_Db_Statement_Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return array
     */
    public function getQueueInfo()
    {
        $select = $this->getConnection()->select()
            ->from(
                [$this->getMainTable()],
                [
                    'count' => 'COUNT(*)',
                    'oldest' => 'MIN(created)',
                ]
            );

        $queueInfo = $this->getConnection()->query($select)->fetch();

        if (!$queueInfo['oldest']) {
            $queueInfo['oldest'] = '[no jobs in indexing queue]';
        }

        return $queueInfo;
    }
}
