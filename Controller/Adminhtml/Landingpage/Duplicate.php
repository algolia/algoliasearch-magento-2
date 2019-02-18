<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Landingpage;

use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;

class Duplicate extends AbstractAction
{
    /** @return \Magento\Framework\View\Result\Page */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $landingPageId = (int) $this->getRequest()->getParam('id');
        if (!$landingPageId) {
            $this->messageManager->addErrorMessage(__('The landing page to duplicate does not exist.'));

            return $resultRedirect->setPath('*/*/');
        }

        /** @var \Algolia\AlgoliaSearch\Model\LandingPage $landingPage */
        $landingPage = $this->landingPageFactory->create();
        $landingPage->getResource()->load($landingPage, $landingPageId);

        if (is_null($landingPage)) {
            $this->messageManager->addErrorMessage(__('This landing page does not exists.'));

            return $resultRedirect->setPath('*/*/');
        }

        $newLandingPage = $this->duplicateLandingPage($landingPage);

        try {
            $newLandingPage->getResource()->save($newLandingPage);
            $this->copyQueryRules($landingPage->getId(), $newLandingPage->getId());

            $this->coreRegistry->register('algoliasearch_landing_page', $newLandingPage);
            $this->messageManager->addSuccessMessage(__('The duplicated landing page has been saved.'));

            return $resultRedirect->setPath('*/*/edit', ['id' => $newLandingPage->getId()]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while saving the duplicated landing page. %1', $e->getMessage())
            );
        }

        $this->messageManager->addErrorMessage(__('An error occurred during the landing page duplication.'));

        return $resultRedirect->setPath('*/*/');
    }

    private function duplicateLandingPage($landingPage)
    {
        /** @var \Algolia\AlgoliaSearch\Model\LandingPage $newLandingPage */
        $newLandingPage = $this->landingPageFactory->create();
        $newLandingPage->setData($landingPage->getData());
        $newLandingPage->setId(null);
        $newLandingPage->setTitle($newLandingPage->getTitle() . ' (duplicated)');
        $newLandingPage->setUrlKey($newLandingPage->getUrlKey() . '-' . time());

        return $newLandingPage;
    }

    private function copyQueryRules($landingPageFromId, $landingPageToId)
    {
        $stores = [];
        if ($landingPageFromId) {
            foreach ($this->storeManager->getStores() as $store) {
                if ($store->getIsActive()) {
                    $stores[] = $store->getId();
                }
            }
        } else {
            $stores[] = $data['store_id'];
        }

        foreach ($stores as $storeId) {
            $this->merchandisingHelper->copyQueryRules(
                $storeId,
                $landingPageFromId,
                $landingPageToId,
                'landingpage'
            );
        }
    }
}
