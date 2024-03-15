<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Landingpage;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Model\LandingPage;
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

        /** @var LandingPage $landingPage */
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
            $this->backendSession->setData('algoliasearch_landing_page', $newLandingPage);
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

    /**
     * @param LandingPage $landingPage
     * @return LandingPage
     */
    private function duplicateLandingPage(LandingPage $landingPage): LandingPage
    {
        /** @var LandingPage $newLandingPage */
        $newLandingPage = $this->landingPageFactory->create();
        $newLandingPage->setData($landingPage->getData());
        $newLandingPage->setId(null);
        $newLandingPage->setTitle($newLandingPage->getTitle() . ' (duplicated)');
        $newLandingPage->setUrlKey($newLandingPage->getUrlKey() . '-' . time());

        return $newLandingPage;
    }

    /**
     * @param int $landingPageFromId
     * @param int $landingPageToId
     * @return void
     * @throws AlgoliaException|\Magento\Framework\Exception\NoSuchEntityException
     */
    private function copyQueryRules(int $landingPageFromId, int $landingPageToId): void
    {
        $stores = [];
        if ($landingPageFromId) {
            foreach ($this->storeManager->getStores() as $store) {
                if ($store->getIsActive()) {
                    $stores[] = $store->getId();
                }
            }
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
