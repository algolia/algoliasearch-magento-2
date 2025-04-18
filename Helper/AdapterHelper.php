<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Helper\Adapter\FiltersHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Product\BackendSearch;
use Magento\CatalogSearch\Helper\Data as CatalogSearchDataHelper;

class AdapterHelper
{
    public const INSTANTSEARCH_ORDER_PARAM = 'sortBy';
    public const BACKEND_ORDER_PARAM = 'product_list_order';

    public function __construct(
        protected CatalogSearchDataHelper $catalogSearchHelper,
        protected BackendSearch $backendSearch,
        protected FiltersHelper $filtersHelper,
        protected ConfigHelper $configHelper,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    )
    {}

    /**
     * Get search result from Algolia
     *
     * @return array
     * @internal This method is currently unstable and should not be used. It may be revisited ar fixed in a future version.
     */
    public function getDocumentsFromAlgolia()
    {
        $storeId = $this->getStoreId();
        $query = $this->catalogSearchHelper->getEscapedQueryText();
        $algoliaQuery = $query !== '__empty__' ? $query : '';
        $searchParams = [];
        $targetedIndex = null;
        if ($this->isReplaceCategory() || $this->isSearch() || $this->isLandingPage()) {
            $searchParams = $this->getSearchParams($storeId);

            // This is the first load of a landing page, so we have to get the parameters from the entity
            if ($this->isLandingPage() && $this->filtersHelper->getRawQueryParameter() === null) {
                $searchParams = array_merge(
                    $searchParams,
                    $this->filtersHelper->getLandingPageFilters($storeId)
                );
                $algoliaQuery = $this->filtersHelper->getLandingPageQuery();
            }

            $orderParam = $this->getOrderParam($storeId);
            if ($this->filtersHelper->getRequest()->getParam('sortBy') !== null) {
                $targetedIndex = $this->filtersHelper->getRequest()->getParam($orderParam);
            }
        }

        return $this->backendSearch->getSearchResult($algoliaQuery, $storeId, $searchParams, $targetedIndex);
    }

    /**
     * Get the sort order parameter
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getOrderParam($storeId)
    {
        return self::INSTANTSEARCH_ORDER_PARAM;
    }

    /**
     * Get the search params from the url
     *
     * @param int $storeId
     *
     * @return array
     */
    private function getSearchParams($storeId)
    {
        $searchParams = [];
        $searchParams['facetFilters'] = [];

        // Handle pagination
        $searchParams = array_merge(
            $searchParams,
            $this->filtersHelper->getPaginationFilters()
        );

        // Handle category context
        $searchParams = array_merge(
            $searchParams,
            $this->filtersHelper->getCategoryFilters($storeId)
        );

        // Handle facet filtering
        $searchParams['facetFilters'] = array_merge(
            $searchParams['facetFilters'],
            $this->filtersHelper->getFacetFilters($storeId)
        );

        // Handle disjunctive facets
        $searchParams = array_merge(
            $searchParams,
            $this->filtersHelper->getDisjunctiveFacets($storeId)
        );

        // Handle price filtering
        $searchParams = array_merge(
            $searchParams,
            $this->filtersHelper->getPriceFilters($storeId)
        );

        return $searchParams;
    }

    /**
     * Checks if Algolia is properly configured and enabled
     *
     * @return bool
     */
    public function isAllowed()
    {
        $storeId = $this->getStoreId();

        return
            $this->algoliaCredentialsManager->checkCredentials($storeId)
            && $this->configHelper->isEnabledFrontEnd($storeId)
            && $this->configHelper->makeSeoRequest($storeId);
    }

    /** @return bool */
    public function isSearch()
    {
        return $this->filtersHelper->getRequest()->getFullActionName() === 'catalogsearch_result_index';
    }

    /** @return bool */
    public function isLandingPage()
    {
        $storeId = $this->getStoreId();

        return
            $this->filtersHelper->getRequest()->getFullActionName() === 'algolia_landingpage_view'
            && $this->configHelper->isInstantEnabled($storeId) === true;
    }

    /**
     * Checks if Algolia should replace category results
     *
     * @return bool
     */
    public function isReplaceCategory()
    {
        $storeId = $this->getStoreId();

        return
            $this->filtersHelper->getRequest()->getControllerName() === 'category'
            && $this->configHelper->replaceCategories($storeId) === true
            && $this->configHelper->isInstantEnabled($storeId) === true;
    }

    /**
     * Checks if Algolia should replace advanced search results
     *
     * @return bool
     */
    public function isReplaceAdvancedSearch()
    {
        return
            $this->filtersHelper->getRequest()->getFullActionName() === 'catalogsearch_advanced_result'
            && $this->configHelper->isInstantEnabled($this->getStoreId()) === true;
    }

    private function getStoreId()
    {
        return $this->configHelper->getStoreId();
    }

    public function isInstantEnabled()
    {
        return $this->configHelper->isInstantEnabled($this->getStoreId());
    }

    public function makeSeoRequest()
    {
        return $this->configHelper->makeSeoRequest($this->getStoreId());
    }
}
