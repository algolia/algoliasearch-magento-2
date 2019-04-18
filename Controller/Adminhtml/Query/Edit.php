<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Query;

use Magento\Framework\Controller\ResultFactory;

class Edit extends AbstractAction
{
    /** @return \Magento\Framework\View\Result\Page */
    public function execute()
    {
        $query = $this->initQuery();
        if (is_null($query)) {
            $this->messageManager->addErrorMessage(__('This query does not exists.'));
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

            return $resultRedirect->setPath('*/*/');
        }

        // Set entered data if was error when we do save
        $data = $this->_session->getFormData(true);
        if (!empty($data['query'])) {
            // send the data to the model
            $query->setData($data['query']);
        }

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        $breadcrumbTitle = $query->getId() ? __('Edit query') : __('New query');
        $resultPage
            ->setActiveMenu('Algolia_AlgoliaSearch::manage')
            ->addBreadcrumb(__('Queries'), __('Queries'))
            ->addBreadcrumb($breadcrumbTitle, $breadcrumbTitle);

        $resultPage->getConfig()->getTitle()->prepend(__('Queries'));
        $resultPage->getConfig()->getTitle()->prepend(
            $query->getId()
                ? __('Edit query "%1"', $query->getQueryText())
                : __('New merchandised query')
        );

        return $resultPage;
    }
}
