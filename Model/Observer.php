<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Layout;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Algolia search observer model
 */
class Observer implements ObserverInterface
{
    private $config;
    private $registry;
    private $storeManager;

    public function __construct(ConfigHelper $configHelper, Registry $registry, StoreManagerInterface $storeManager)
    {
        $this->config = $configHelper;
        $this->registry = $registry;
        $this->storeManager = $storeManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->config->isEnabledFrontEnd()) {
            if ($this->config->getApplicationID() && $this->config->getAPIKey()) {
                if ($this->config->isAutoCompleteEnabled() || $this->config->isInstantEnabled()) {
                    /** @var Layout $layout */
                    $layout = $observer->getData('layout');

                    $layout->getUpdate()->addHandle('algolia_search_handle');

                    if ($this->config->isDefaultSelector()) {
                        $layout->getUpdate()->addHandle('algolia_search_handle_with_topsearch');
                    } else {
                        $layout->getUpdate()->addHandle('algolia_search_handle_no_topsearch');
                    }

                    $this->loadPreventBackendRenderingHandle($layout);

                    $this->loadAnalyticsHandle($layout);
                }
            }
        }
    }

    private function loadPreventBackendRenderingHandle(Layout $layout)
    {
        $storeId = $this->storeManager->getStore()->getId();

        if ($this->config->preventBackendRendering() === false) {
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

        $displayMode = $this->config->getBackendRenderingDisplayMode();
        if ($displayMode === 'only_products' && $category->getDisplayMode() === 'PAGE') {
            return;
        }

        $layout->getUpdate()->addHandle('algolia_search_handle_prevent_backend_rendering');
    }

    private function loadAnalyticsHandle(Layout $layout)
    {
        if ($this->config->isClickConversionAnalyticsEnabled() === false) {
            return;
        }

        $layout->getUpdate()->addHandle('algolia_search_handle_click_conversion_analytics');
    }
}
