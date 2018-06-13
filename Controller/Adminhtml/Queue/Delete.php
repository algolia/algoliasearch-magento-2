<?php
/**
 * Module Algolia Algoliasearcgh
 */
namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;

class Delete extends AbstractAction
{
    /**
     * Execute the action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('*/*/index');

        $job = $this->initJob();
        if (is_null($job)) {
            $this->messageManager->addErrorMessage(__('This job does not exists.'));
            return $resultRedirect;
        }

        try {
            $this->jobResourceModel->delete($job);
            $this->messageManager->addNoticeMessage(__('This job has been deleted.'));
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e);
        }

        return $resultRedirect;
    }
}
