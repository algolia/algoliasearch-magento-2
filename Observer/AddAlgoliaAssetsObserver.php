<?php

namespace Algolia\AlgoliaSearch\Observer;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\RenderingManager;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Layout;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Algolia search observer model
 */
class AddAlgoliaAssetsObserver implements ObserverInterface
{
    public function __construct(
        protected ConfigHelper $config,
        protected RenderingManager $renderingManager,
        protected StoreManagerInterface $storeManager,
        protected Http $request,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    ) {}

    /**
     * @throws NoSuchEntityException|AlgoliaException
     */
    public function execute(Observer $observer): void
    {
        $actionName = $this->request->getFullActionName();
        if ($actionName === 'swagger_index_index') {
            return;
        }

        $storeId = $this->storeManager->getStore()->getId();

        if ($this->config->isEnabledFrontEnd($storeId) && $this->algoliaCredentialsManager->checkCredentials($storeId)) {
            /** @var Layout $layout */
            $layout = $observer->getData('layout');

            $this->renderingManager->handleFrontendAssets($layout, $storeId);
            $this->renderingManager->handleBackendRendering($layout, $actionName, $storeId);
        }
    }

}
