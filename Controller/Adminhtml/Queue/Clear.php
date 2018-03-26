<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

class Clear extends Queue
{
    public function execute()
    {
        try {
            $this->db->query('TRUNCATE TABLE '.$this->tableName);

            $status = ['status' => 'ok'];
        } catch (\Exception $e) {
            $status = ['status' => 'ko', 'message' => $e->getMessage()];
        }

        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();

        return $result->setData($status);
    }
}
