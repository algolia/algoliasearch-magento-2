<?php

namespace Algolia\AlgoliaSearch\Block;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\CollectionDataSourceInterface;
use Magento\Framework\DataObject;

class Configuration extends Algolia implements CollectionDataSourceInterface
{
    public function isSearchPage()
    {
        if ($this->getConfigHelper()->isInstantEnabled()) {
            /** @var Http $request */
            $request = $this->getRequest();

            if ($request->getFullActionName() === 'catalogsearch_result_index') {
                return true;
            }

            if ($this->getConfigHelper()->replaceCategories() && $request->getControllerName() === 'category') {
                $category = $this->getCurrentCategory();
                if ($category && $category->getDisplayMode() !== 'PAGE') {
                    return true;
                }
            }
        }

        return false;
    }

    public function getConfiguration()
    {
        $config = $this->getConfigHelper();

        $catalogSearchHelper = $this->getCatalogSearchHelper();

        $coreHelper = $this->getCoreHelper();

        $categoryHelper = $this->getCategoryHelper();

        $productHelper = $this->getProductHelper();

        $algoliaHelper = $this->getAlgoliaHelper();

        $baseUrl = rtrim($this->getBaseUrl(), '/');

        $currencyCode = $this->getCurrencyCode();
        $currencySymbol = $this->getCurrencySymbol();
        $priceFormat = $this->getPriceFormat();

        $customerGroupId = $this->getGroupId();

        $priceKey = $this->getPriceKey();

        $query = '';
        $refinementKey = '';
        $refinementValue = '';
        $path = '';
        $level = '';
        $categoryId = '';

        $addToCartParams = $this->getAddToCartParams();

        /** @var Http $request */
        $request = $this->getRequest();

        /**
         * Handle category replacement
         */

        $isCategoryPage = false;
        if ($config->isInstantEnabled()
            && $config->replaceCategories()
            && $request->getControllerName() === 'category') {
            $category = $this->getCurrentCategory();

            if ($category && $category->getDisplayMode() !== 'PAGE') {
                $category->getUrlInstance()->setStore($this->getStoreId());

                $categoryId = $category->getId();

                $level = -1;
                foreach ($category->getPathIds() as $treeCategoryId) {
                    if ($path !== '') {
                        $path .= ' /// ';
                    }

                    $path .= $categoryHelper->getCategoryName($treeCategoryId, $this->getStoreId());

                    if ($path) {
                        $level++;
                    }
                }

                $isCategoryPage = true;
            }
        }

        $productId = null;
        if ($config->isClickConversionAnalyticsEnabled() && $request->getFullActionName() === 'catalog_product_view') {
            $productId = $this->getCurrentProduct()->getId();
        }

        /**
         * Handle search
         */
        $facets = $config->getFacets();

        $areCategoriesInFacets = $this->areCategoriesInFacets($facets);

        if ($config->isInstantEnabled()) {
            $pageIdentifier = $request->getFullActionName();

            if ($pageIdentifier === 'catalogsearch_result_index') {
                $query = $this->getRequest()->getParam($catalogSearchHelper->getQueryParamName());

                if ($query === '__empty__') {
                    $query = '';
                }

                $refinementKey = $this->getRequest()->getParam('refinement_key');

                if ($refinementKey !== null) {
                    $refinementValue = $query;
                    $query = '';
                } else {
                    $refinementKey = '';
                }
            }
        }

        $algoliaJsConfig = [
            'instant' => [
                'enabled' => $config->isInstantEnabled(),
                'selector' => $config->getInstantSelector(),
                'isAddToCartEnabled' => $config->isAddToCartEnable(),
                'addToCartParams' => $addToCartParams,
                'infiniteScrollEnabled' => $config->isInfiniteScrollEnabled(),
                'urlTrackedParameters' => $this->getUrlTrackedParameters(),
            ],
            'autocomplete' => [
                'enabled' => $config->isAutoCompleteEnabled(),
                'selector' => $config->getAutocompleteSelector(),
                'sections' => $config->getAutocompleteSections(),
                'nbOfProductsSuggestions' => $config->getNumberOfProductsSuggestions(),
                'nbOfCategoriesSuggestions' => $config->getNumberOfCategoriesSuggestions(),
                'nbOfQueriesSuggestions' => $config->getNumberOfQueriesSuggestions(),
                'isDebugEnabled' => $config->isAutocompleteDebugEnabled(),
            ],
            'extensionVersion' => $config->getExtensionVersion(),
            'applicationId' => $config->getApplicationID(),
            'indexName' => $coreHelper->getBaseIndexName(),
            'apiKey' => $algoliaHelper->generateSearchSecuredApiKey(
                $config->getSearchOnlyAPIKey(),
                $config->getAttributesToRetrieve($customerGroupId)
            ),
            'facets' => $facets,
            'areCategoriesInFacets' => $areCategoriesInFacets,
            'hitsPerPage' => (int) $config->getNumberOfProductResults(),
            'sortingIndices' => array_values($config->getSortingIndices(
                $coreHelper->getIndexName($productHelper->getIndexNameSuffix()), null, $customerGroupId
            )),
            'isSearchPage' => $this->isSearchPage(),
            'isCategoryPage' => $isCategoryPage,
            'removeBranding' => (bool) $config->isRemoveBranding(),
            'productId' => $productId,
            'priceKey' => $priceKey,
            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
            'priceFormat' => $priceFormat,
            'maxValuesPerFacet' => (int) $config->getMaxValuesPerFacet(),
            'autofocus' => true,
            'request' => [
                'query' => html_entity_decode($query),
                'refinementKey' => $refinementKey,
                'refinementValue' => $refinementValue,
                'categoryId' => $categoryId,
                'path' => $path,
                'level' => $level,
            ],
            'showCatsNotIncludedInNavigation' => $config->showCatsNotIncludedInNavigation(),
            'showSuggestionsOnNoResultsPage' => $config->showSuggestionsOnNoResultsPage(),
            'baseUrl' => $baseUrl,
            'popularQueries' => $config->getPopularQueries(),
            'useAdaptiveImage' => $config->useAdaptiveImage(),
            'urls' => [
                'logo' => $this->getViewFileUrl('Algolia_AlgoliaSearch::images/search-by-algolia.svg'),
            ],
            'ccAnalytics' => [
                'ISSelector' => $config->getClickConversionAnalyticsISSelector(),
                'conversionAnalyticsMode' => $config->getConversionAnalyticsMode(),
                'addToCartSelector' => $config->getConversionAnalyticsAddToCartSelector(),
                'orderedProductIds' => $this->getOrderedProductIds($config, $request),
            ],
            'analytics' => $config->getAnalyticsConfig(),
            'now' => $this->getTimestamp(),
            'translations' => [
                'to' => __('to'),
                'or' => __('or'),
                'go' => __('Go'),
                'popularQueries' => __('You can try one of the popular search queries'),
                'seeAll' => __('See all products'),
                'allDepartments' => __('All departments'),
                'seeIn' => __('See products in'),
                'orIn' => __('or in'),
                'noProducts' => __('No products for query'),
                'noResults' => __('No results'),
                'refine' => __('Refine'),
                'selectedFilters' => __('Selected Filters'),
                'clearAll' => __('Clear all'),
                'previousPage' => __('Previous page'),
                'nextPage' => __('Next page'),
                'searchFor' => __('Search for products'),
                'relevance' => __('Relevance'),
                'categories' => __('Categories'),
                'products' => __('Products'),
                'searchBy' => __('Search by'),
                'searchForFacetValuesPlaceholder' => __('Search for other ...'),
                'showMore' => __('Show more products'),
            ],
        ];

        $transport = new DataObject($algoliaJsConfig);
        $this->_eventManager->dispatch('algolia_after_create_configuration', ['configuration' => $transport]);
        $algoliaJsConfig = $transport->getData();

        return $algoliaJsConfig;
    }

    private function areCategoriesInFacets($facets)
    {
        foreach ($facets as $facet) {
            if ($facet['attribute'] === 'categories') {
                return true;
            }
        }

        return false;
    }

    private function getUrlTrackedParameters()
    {
        $urlTrackedParameters = ['query', 'attribute:*', 'index'];

        if ($this->getConfigHelper()->isInfiniteScrollEnabled() === false) {
            $urlTrackedParameters[] = 'page';
        }

        return $urlTrackedParameters;
    }

    private function getOrderedProductIds(ConfigHelper $configHelper, Http $request)
    {
        $ids = [];

        if ($configHelper->getConversionAnalyticsMode() === 'disabled'
            || $request->getFrontName() !== 'checkout'
            || $request->getActionName() !== 'success') {
            return $ids;
        }

        $lastOrder = $this->getLastOrder();
        if (!$lastOrder) {
            return $ids;
        }

        $items = $lastOrder->getItems();
        foreach ($items as $item) {
            $ids[] = $item->getProductId();
        }

        return $ids;
    }
}
