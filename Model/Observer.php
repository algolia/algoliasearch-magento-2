<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
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
    private $config;
    private $registry;
    private $storeManager;
    private $pageConfig;

    public function __construct(
        ConfigHelper $configHelper,
        Registry $registry,
        StoreManagerInterface $storeManager,
        PageConfig $pageConfig
    ) {
        $this->config = $configHelper;
        $this->registry = $registry;
        $this->storeManager = $storeManager;
        $this->pageConfig = $pageConfig;
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

                    $this->addBodyCss();
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

    private function addBodyCss()
    {
        $storeId = $this->storeManager->getStore()->getId();
        if ($this->config->isBackendRenderingEnabled($storeId)) {
            $this->pageConfig->addBodyClass('algolia-rendering');
        }
    }
}
