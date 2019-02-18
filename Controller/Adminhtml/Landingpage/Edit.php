<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Landingpage;

use Magento\Framework\Controller\ResultFactory;

class Edit extends AbstractAction
{
    /** @return \Magento\Framework\View\Result\Page */
    public function execute()
    {
        $landingPage = $this->initLandingPage();
        if (is_null($landingPage)) {
            $this->messageManager->addErrorMessage(__('This landing page does not exists.'));
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

            return $resultRedirect->setPath('*/*/');
        }

        // Set entered data if was error when we do save
        $data = $this->_session->getFormData(true);
        if (!empty($data['landing_page'])) {
            // send the data to the model
            $landingPage->setData($data['landing_page']);
        }

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $breadcrumbTitle = $landingPage->getId() ? __('Edit landing page') : __('New landing page');
        $resultPage
            ->setActiveMenu('Algolia_AlgoliaSearch::manage')
            ->addBreadcrumb(__('Landing pages'), __('Landing pages'))
            ->addBreadcrumb($breadcrumbTitle, $breadcrumbTitle);

        $resultPage->getConfig()->getTitle()->prepend(__('Landing Pages'));
        $resultPage->getConfig()->getTitle()->prepend(
            $landingPage->getId()
                ? __('Edit landing page "%1"', $landingPage->getTitle())
                : __('New landing page')
        );

        return $resultPage;
    }
}
