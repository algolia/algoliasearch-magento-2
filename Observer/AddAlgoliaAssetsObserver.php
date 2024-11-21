<?php

namespace Algolia\AlgoliaSearch\Observer;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Registry\CurrentCategory;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Algolia search observer model
 */
class AddAlgoliaAssetsObserver implements ObserverInterface
{
    public function __construct(
        protected ConfigHelper $config,
        protected CurrentCategory $category,
        protected StoreManagerInterface $storeManager,
        protected PageConfig $pageConfig,
        protected Http $request,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    )
    {}

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

    private function loadPreventBackendRenderingHandle(Layout $layout, int $storeId): void
    {
        if (!$this->config->preventBackendRendering($storeId)) {
            return;
        }

        $category = $this->category->get();

        if (!$category->getId()) {
            return;
        }

        if (!$this->config->replaceCategories($storeId)) {
            return;
        }

        $displayMode = $this->config->getBackendRenderingDisplayMode($storeId);
        if ($displayMode === 'only_products'
            && $category->getData('display_mode') === \Magento\Catalog\Model\Category::DM_PAGE) {
            return;
        }

        $layout->getUpdate()->addHandle('algolia_search_handle_prevent_backend_rendering');
    }
}
