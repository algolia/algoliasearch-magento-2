<?php
/**
 * Module Algolia Algoliasearch
 */
namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;

class View extends AbstractAction
{
    /**
     * Execute the action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $job = $this->initJob();
        if (is_null($job)) {
            $this->messageManager->addErrorMessage(__('This job does not exists.'));
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('*/*/');
        }

        $breadMain = __('Algolia Indexing Queue');

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Algolia_AlgoliaSearch::manage');
        $resultPage->getConfig()->getTitle()->prepend($breadMain);

        return $resultPage;

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $breadcrumbTitle = __('View Job');
        $resultPage
            ->setActiveMenu('Algolia_AlgoliaSearch::manage')
            ->addBreadcrumb(__('Indexing Queue'), __('Indexing Queue'))
            ->addBreadcrumb($breadcrumbTitle, $breadcrumbTitle);

        $resultPage->getConfig()->getTitle()->prepend(__('Indexing Queue'));
        $resultPage->getConfig()->getTitle()->prepend(__("View Job #%1", $job->getIdentifier()));

        return $resultPage;
    }
}
