<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

class Info extends Queue
{
    public function execute()
    {
        $size = (int) $this->db->query('SELECT COUNT(*) as total_count FROM '.$this->tableName)->fetchColumn(0);
        $maxJobsPerSingleRun = $this->configHelper->getNumberOfJobToRun();

        $etaMinutes = ceil($size / $maxJobsPerSingleRun) * 5; // 5 - assuming the queue runner runs every 5 minutes

        $eta = $etaMinutes . ' minutes';
        if ($etaMinutes > 60) {
            $hours = floor($etaMinutes / 60);
            $restMinutes = $etaMinutes % 60;

            $eta = $hours . ' hours ' . $restMinutes . ' minutes';
        }

        $queueInfo = [
            'isEnabled' => $this->configHelper->isQueueActive(),
            'currentSize' => $size,
            'eta' => $eta,
        ];

        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();

        return $result->setData($queueInfo);
    }
}
