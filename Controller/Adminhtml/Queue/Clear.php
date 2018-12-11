<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Magento\Framework\Controller\ResultFactory;

class Clear extends AbstractAction
{
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('*/*/index');

        try {
            $this->db->query('TRUNCATE TABLE ' . $this->tableName);
            $this->messageManager->addNoticeMessage(__('Queue has been cleared.'));
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e);
        }

        return $resultRedirect;
    }
}
