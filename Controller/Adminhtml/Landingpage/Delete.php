<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Landingpage;

use Magento\Framework\Controller\ResultFactory;

class Delete extends AbstractAction
{
    /** @return \Magento\Framework\View\Result\Page */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $landingPageId = $this->getRequest()->getParam('id');
        if ($landingPageId) {
            try {
                /** @var \Algolia\AlgoliaSearch\Model\LandingPage $landingPage */
                $landingPage = $this->landingPageFactory->create();
                $landingPage->getResource()->load($landingPage, $landingPageId);
                $landingPage->getResource()->delete($landingPage);
                $this->deleteQueryRules($landingPage);

                $this->messageManager->addSuccessMessage(__('The landing page has been deleted.'));

                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());

                return $resultRedirect->setPath('*/*/edit', ['landing_page_id' => $landingPageId]);
            }
        }
        $this->messageManager->addErrorMessage(__('The landing page to delete does not exist.'));

        return $resultRedirect->setPath('*/*/');
    }

    private function deleteQueryRules($landingPage)
    {
        $stores = [];
        if ($landingPage->getStoreId() == 0) {
            foreach ($this->storeManager->getStores() as $store) {
                if ($store->getIsActive()) {
                    $stores[] = $store->getId();
                }
            }
        } else {
            $stores[] = $landingPage->getStoreId();
        }

        foreach ($stores as $storeId) {
            $this->merchandisingHelper->deleteQueryRule(
                $storeId,
                $landingPage->getId(),
                'landingpage'
            );
        }
    }
}
