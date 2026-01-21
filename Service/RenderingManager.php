<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Registry\CurrentCategory;
use Magento\Catalog\Model\Category;
use Magento\Framework\View\Layout;

class RenderingManager
{
    public function __construct(
        protected AutocompleteHelper    $autocompleteConfigHelper,
        protected InstantSearchHelper   $instantSearchConfigHelper,
        protected CurrentCategory       $category
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

        $this->addHandle($layout, 'algolia_search_handle');
    }

    /**
     * @param Layout $layout
     * @param string $actionName
     * @param int $storeId
     * @return void
     */
    public function handleBackendRendering(Layout $layout, string $actionName, int $storeId): void
    {
        if (!$this->shouldPreventBackendRendering($actionName, $storeId)) {
            return;
        }

        $this->addHandle($layout, 'algolia_search_handle_prevent_backend_rendering');
    }

    /**
     * Backend rendering is prevented by default for InstantSearch enabled pages.
     *
     * If backend rendering is desired - install the Algolia_SearchAdapter extension.
     * See https://github.com/algolia/algoliasearch-adapter-magento-2
     */
    public function shouldPreventBackendRendering(string $actionName, int $storeId): bool
    {
        if (!$this->instantSearchConfigHelper->isEnabled($storeId) // Backend render only prevented with InstantSearch
            || !$this->isSearchPage($actionName) // Route must be a category or the catalogsearch page
            || !$this->shouldHandleCurrentCategory($storeId)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Only handle categories if Algolia "browse" mode is enabled and category features a PLP
     */
    protected function shouldHandleCurrentCategory(int $storeId): bool
    {
        $category = $this->category->get();

        if ($category->getId()) {
            if (!$this->instantSearchConfigHelper->shouldReplaceCategories($storeId)) {
                return false;
            }

            if ($category->getDisplayMode() === Category::DM_PAGE) {
                return false;
            }
        }

        return true;
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
