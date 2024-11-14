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
    protected $config;
    protected $registry;
    protected $storeManager;
    protected $pageConfig;
    protected $request;

    /**
     * @param ConfigHelper $configHelper
     * @param Registry $registry
     * @param StoreManagerInterface $storeManager
     * @param PageConfig $pageConfig
     * @param \Magento\Framework\App\Request\Http $http
     */
    public function __construct(
        ConfigHelper $configHelper,
        Registry $registry,
        StoreManagerInterface $storeManager,
        PageConfig $pageConfig,
        \Magento\Framework\App\Request\Http $http
    ) {
        $this->config = $configHelper;
        $this->registry = $registry;
        $this->storeManager = $storeManager;
        $this->pageConfig = $pageConfig;
        $this->request = $http;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $actionName = $this->request->getFullActionName();
        if ($actionName === 'swagger_index_index') {
            return $this;
        }
        if ($this->config->isEnabledFrontEnd()) {
            if ($this->config->getApplicationID() && $this->config->getAPIKey()) {
                if ($this->config->isAutoCompleteEnabled() || $this->config->isInstantEnabled()) {
                    /** @var Layout $layout */
                    $layout = $observer->getData('layout');
                    $layout->getUpdate()->addHandle('algolia_search_handle');

                    $this->loadPreventBackendRenderingHandle($layout);
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
}
