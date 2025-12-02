<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\QueueArchive;

use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;

class View extends AbstractAction
{
    /** @return Page */
    public function execute()
    {
        $job = $this->initJob();
        if ($job === null) {
            $this->messageManager->addErrorMessage(__('This job does not exist.'));
            /** @var Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

            return $resultRedirect->setPath('*/*/');
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $breadcrumbTitle = __('View Queue Archive');
        $resultPage
            ->setActiveMenu('Algolia_AlgoliaSearch::manage')
            ->addBreadcrumb(__('Queue Archive'), __('Queue Archive'))
            ->addBreadcrumb($breadcrumbTitle, $breadcrumbTitle);

        $resultPage->getConfig()->getTitle()->prepend(__('Queue Archive'));
        $resultPage->getConfig()->getTitle()->prepend(__('View Queue Archive #%1', $job->getId()));

        return $resultPage;
    }
}
