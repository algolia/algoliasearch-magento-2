<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Magento\Framework\Controller\ResultFactory;

class View extends AbstractAction
{
    /** @return \Magento\Framework\View\Result\Page */
    public function execute()
    {
        $job = $this->initJob();
        if (is_null($job)) {
            $this->messageManager->addErrorMessage(__('This job does not exists.'));
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

            return $resultRedirect->setPath('*/*/');
        }

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $breadcrumbTitle = __('View Job');
        $resultPage
            ->setActiveMenu('Algolia_AlgoliaSearch::manage')
            ->addBreadcrumb(__('Indexing Queue'), __('Indexing Queue'))
            ->addBreadcrumb($breadcrumbTitle, $breadcrumbTitle);

        $resultPage->getConfig()->getTitle()->prepend(__('Indexing Queue'));
        $resultPage->getConfig()->getTitle()->prepend(__('View Job #%1', $job->getId()));

        return $resultPage;
    }
}
