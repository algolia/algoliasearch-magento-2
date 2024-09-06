<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Algolia search observer model
 */
class Observer implements ObserverInterface
{
    public function __construct(
        protected ConfigHelper $config,
        protected Registry $registry,
        protected StoreManagerInterface $storeManager,
        protected PageConfig $pageConfig,
        protected Http $request,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    )
    {}

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $actionName = $this->request->getFullActionName();
        if ($actionName === 'swagger_index_index') {
            return $this;
        }
        $storeId = $this->storeManager->getStore()->getId();
        if ($this->config->isEnabledFrontEnd($storeId)) {
            if ($this->algoliaCredentialsManager->checkCredentials($storeId)) {
                if ($this->config->isAutoCompleteEnabled($storeId) || $this->config->isInstantEnabled($storeId)) {
                    /** @var Layout $layout */
                    $layout = $observer->getData('layout');
                    $layout->getUpdate()->addHandle('algolia_search_handle');

                    $this->loadPreventBackendRenderingHandle($layout, $storeId);
                }
            }
        }
    }

    private function loadPreventBackendRenderingHandle(Layout $layout, int $storeId)
    {
        if ($this->config->preventBackendRendering($storeId) === false) {
            return;
        }

        /** @var \Magento\Catalog\Model\Category $category */
        $category = $this->registry->registry('current_category');
        if (!$category) {
            return;
        }

        if (!$this->config->replaceCategories($storeId)) {
            return;
        }

        $displayMode = $this->config->getBackendRenderingDisplayMode($storeId);
        if ($displayMode === 'only_products' && $category->getDisplayMode() === 'PAGE') {
            return;
        }

        $layout->getUpdate()->addHandle('algolia_search_handle_prevent_backend_rendering');
    }
}
