<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Registry\CurrentCategory;
use Magento\Catalog\Model\Category;
use Magento\Framework\View\Layout;
use Magento\Store\Model\StoreManagerInterface;

class RenderingManager
{
    public function __construct(
        protected ConfigHelper $config,
        protected AutocompleteHelper $autocompleteConfigHelper,
        protected InstantSearchHelper $instantSearchConfigHelper,
        protected CurrentCategory $category,
        protected StoreManagerInterface $storeManager
    ) {}

    /**
     * @param Layout $layout
     * @param int $storeId
     * @return void
     */
    public function handleFrontendAssets(Layout $layout, int $storeId): void
    {
        // If an Algolia frontend feature is enabled, add the frontend assets
        if (!$this->hasAlgoliaFrontend($storeId)) {
           return;
        }

        $this->addHandle($layout,'algolia_search_handle');
    }

    /**
     * @param Layout $layout
     * @param string $actionName
     * @param int $storeId
     * @return void
     */
    public function handleBackendRendering(Layout $layout, string $actionName, int $storeId): void
    {
        // If the page is not a category or the catalogsearch page or if no Algolia frontend feature is enabled, no need to go further
        if (!$this->isSearchPage($actionName) || !$this->hasAlgoliaFrontend($storeId)) {
            return;
        }

        // @todo replace this check with the new backend rendering feature (MAGE-1325)
        if (!$this->config->preventBackendRendering($storeId)) {
            return;
        }

        $category = $this->category->get();
        // Legacy check regarding category display mode (we don't want to hide the static blocks if there's not product list
        if ($category->getId() && $this->instantSearchConfigHelper->shouldReplaceCategories($storeId)) {
            $displayMode = $this->config->getBackendRenderingDisplayMode($storeId);

            if ($displayMode === 'only_products' && $category->getDisplayMode() === Category::DM_PAGE) {
                return;
            }
        }

        $this->addHandle($layout, 'algolia_search_handle_prevent_backend_rendering');
    }

    /**
     * @param Layout $layout
     * @param string $handleName
     * @return void
     */
    protected function addHandle(Layout $layout, string $handleName): void
    {
        $layout->getUpdate()->addHandle($handleName);
    }

    /**
     * @param int $storeId
     * @return bool
     */
    protected function hasAlgoliaFrontend(int $storeId): bool
    {
        return $this->autocompleteConfigHelper->isEnabled($storeId) ||
            $this->instantSearchConfigHelper->isEnabled($storeId);
    }

    /**
     * @param string $actionName
     * @return bool
     */
    protected function isSearchPage(string $actionName): bool
    {
        return $actionName === 'catalog_category_view' || $actionName === 'catalogsearch_result_index';
    }
}
