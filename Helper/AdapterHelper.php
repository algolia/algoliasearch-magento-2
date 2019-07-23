<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Helper\Adapter\FiltersHelper;
use Algolia\AlgoliaSearch\Helper\Data as AlgoliaDataHelper;
use Magento\CatalogSearch\Helper\Data as CatalogSearchDataHelper;

class AdapterHelper
{
    /** @var CatalogSearchDataHelper */
    private $catalogSearchHelper;

    /** @var AlgoliaDataHelper */
    private $algoliaHelper;

    /** @var FiltersHelper */
    private $filtersHelper;

    /** @var ConfigHelper */
    private $configHelper;

    /**
     * @param CatalogSearchDataHelper $catalogSearchHelper
     * @param AlgoliaDataHelper $algoliaHelper
     * @param FiltersHelper $filtersHelper
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        CatalogSearchDataHelper $catalogSearchHelper,
        AlgoliaDataHelper $algoliaHelper,
        FiltersHelper $filtersHelper,
        ConfigHelper $configHelper
    ) {
        $this->catalogSearchHelper = $catalogSearchHelper;
        $this->algoliaHelper = $algoliaHelper;
        $this->filtersHelper = $filtersHelper;
        $this->configHelper = $configHelper;
    }

    /**
     * Get search result from Algolia
     *
     * @return array
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

            if ($this->filtersHelper->getRequest()->getParam('sortBy') !== null) {
                $targetedIndex = $this->filtersHelper->getRequest()->getParam('sortBy');
            }
        }

        return $this->algoliaHelper->getSearchResult($algoliaQuery, $storeId, $searchParams, $targetedIndex);
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
            $this->filtersHelper->getCategoryFilters()
        );

        // Handle facet filtering
        $searchParams['facetFilters'] = array_merge(
            $searchParams['facetFilters'],
            $this->filtersHelper->getFacetFilters($storeId)
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
            $this->configHelper->getApplicationID($storeId)
            && $this->configHelper->getAPIKey($storeId)
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
