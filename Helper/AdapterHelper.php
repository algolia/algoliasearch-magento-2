<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Helper\Data as AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\Entity\Product\AttributeHelper;
use Magento\CatalogSearch\Helper\Data;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

class AdapterHelper
{
    /** @var ConfigHelper */
    private $config;

    /** @var Data */
    private $catalogSearchHelper;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var Registry */
    private $registry;

    /** @var CustomerSession */
    private $customerSession;

    /** @var AlgoliaHelper */
    private $algoliaHelper;

    /** @var AttributeHelper\ */
    private $attributeHelper;

    /** @var Http */
    private $request;

    /**
     * @param ConfigHelper $config
     * @param Data $catalogSearchHelper
     * @param StoreManagerInterface $storeManager
     * @param Registry $registry
     * @param CustomerSession $customerSession
     * @param AlgoliaHelper $algoliaHelper
     * @param AttributeHelper $attributeHelper
     * @param Http $request
     */
    public function __construct(
        ConfigHelper $config,
        Data $catalogSearchHelper,
        StoreManagerInterface $storeManager,
        Registry $registry,
        CustomerSession $customerSession,
        AlgoliaHelper $algoliaHelper,
        AttributeHelper $attributeHelper,
        Http $request
    ) {
        $this->config = $config;
        $this->catalogSearchHelper = $catalogSearchHelper;
        $this->storeManager = $storeManager;
        $this->registry = $registry;
        $this->customerSession = $customerSession;
        $this->algoliaHelper = $algoliaHelper;
        $this->attributeHelper = $attributeHelper;
        $this->request = $request;
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
        if ($this->isReplaceCategory($storeId) || $this->isSearch($storeId)) {
            $searchParams = $this->getSearchParams($storeId);

            if (!is_null($this->request->getParam('sortBy'))) {
                $targetedIndex = $this->request->getParam('sortBy');
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
            $this->getPaginationFilters()
        );

        // Handle category context
        $searchParams = array_merge(
            $searchParams,
            $this->getCategoryFilters()
        );

        // Handle facet filtering
        $searchParams['facetFilters'] = array_merge(
            $searchParams['facetFilters'],
            $this->getFacetFilters($storeId)
        );

        // Handle price filtering
        $searchParams = array_merge(
            $searchParams,
            $this->getPriceFilters($storeId)
        );

        return $searchParams;
    }

    /**
     * Get the pagination filters from the url
     *
     * @return array
     */
    private function getPaginationFilters()
    {
        $paginationFilter = [];
        $page = !is_null($this->request->getParam('page')) ?
            (int) $this->request->getParam('page') - 1 :
            0;
        $paginationFilter['page'] = $page;

        return $paginationFilter;
    }

    /**
     * Get the category filters from the context
     *
     * @return array
     */
    private function getCategoryFilters()
    {
        $categoryFilter = [];
        $category = $this->registry->registry('current_category');
        if ($category) {
            $categoryFilter['facetFilters'][] = 'categoryIds:' . $category->getEntityId();
        }

        return $categoryFilter;
    }

    /**
     * Get the facet filters from the url
     *
     * @param int $storeId
     *
     * @return array
     */
    private function getFacetFilters($storeId)
    {
        $facetFilters = [];

        foreach ($this->config->getFacets($storeId) as $facet) {
            if (is_null($this->request->getParam($facet['attribute']))) {
                continue;
            }

            $facetValues = is_array($this->request->getParam($facet['attribute'])) ?
                $this->request->getParam($facet['attribute']) :
                explode('~', $this->request->getParam($facet['attribute']));

            // Backward compatibility with native Magento filtering
            if (!$this->config->isInstantEnabled($storeId) && $this->isSearch()) {
                foreach ($facetValues as $key => $facetValue) {
                    if (is_numeric($facetValue)) {
                        $facetValues[$key] = $this->getAttributeOptionLabelFromId($facet['attribute'], $facetValue);
                    }
                }
            }

            if ($facet['attribute'] == 'categories') {
                $level = '.level' . (count($facetValues) - 1);
                $facetFilters[] = $facet['attribute'] . $level . ':' . implode(' /// ', $facetValues);
                continue;
            }

            if ($facet['type'] === 'conjunctive') {
                foreach ($facetValues as $key => $facetValue) {
                    $facetFilters[] = $facet['attribute'] . ':' . $facetValue;
                }
            }

            if ($facet['type'] === 'disjunctive') {
                if (count($facetValues) > 1) {
                    foreach ($facetValues as $key => $facetValue) {
                        $facetValues[$key] = $facet['attribute'] . ':' . $facetValue;
                    }
                    $facetFilters[] = $facetValues;
                }
                if (count($facetValues) == 1) {
                    $facetFilters[] = $facet['attribute'] . ':' . $facetValues[0];
                }
            }
        }

        return $facetFilters;
    }

    /**
     * Get the price filters from the url
     *
     * @param int $storeId
     *
     * @return array
     */
    private function getPriceFilters($storeId)
    {
        $priceFilters = [];

        // Handle price filtering
        $currencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
        $priceSlider = 'price.' . $currencyCode . '.default';

        if ($this->config->isCustomerGroupsEnabled($storeId)) {
            $groupId = $this->customerSession->isLoggedIn() ?
                $this->customerSession->getCustomer()->getGroupId() :
                0;
            $priceSlider = 'price.' . $currencyCode . '.group_' . $groupId;
        }

        $paramPriceSlider = str_replace('.', '_', $priceSlider);

        if (!is_null($this->request->getParam($paramPriceSlider))) {
            $pricesFilter = $this->request->getParam($paramPriceSlider);
            $prices = explode(':', $pricesFilter);

            if (count($prices) == 2) {
                if ($prices[0] != '') {
                    $priceFilters['numericFilters'][] = $priceSlider . '>=' . $prices[0];
                }
                if ($prices[1] != '') {
                    $priceFilters['numericFilters'][] = $priceSlider . '<=' . $prices[1];
                }
            }
        }

        return $priceFilters;
    }

    /**
     * Get the label of an attribute option from its id
     *
     * @param string $attribute
     * @param int $value
     *
     * @return string
     */
    private function getAttributeOptionLabelFromId($attribute, $value)
    {
        $attributeOptionLabel = '';
        $attrInfo = $this->attributeHelper->getAttributeInfo(
            \Magento\Catalog\Model\Product::ENTITY,
            $attribute
        );

        if ($attrInfo->getAttributeId()) {
            $option = $this->attributeHelper->getAttributeOptionById(
                $attrInfo->getAttributeId(),
                $value
            );

            if (is_array($option->getData())) {
                $attributeOptionLabel = $option['value'];
            }
        }

        return $attributeOptionLabel;
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
            $this->config->getApplicationID($storeId)
            && $this->config->getAPIKey($storeId)
            && $this->config->isEnabledFrontEnd($storeId)
            && $this->config->makeSeoRequest($storeId);
    }

    /** @return bool */
    public function isSearch()
    {
        return $this->request->getFullActionName() === 'catalogsearch_result_index';
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
            $this->request->getControllerName() === 'category'
            && $this->config->replaceCategories($storeId) === true
            && $this->config->isInstantEnabled($storeId) === true;
    }

    /**
     * Checks if Algolia should replace advanced search results
     *
     * @return bool
     */
    public function isReplaceAdvancedSearch()
    {
        return
            $this->request->getFullActionName() === 'catalogsearch_advanced_result'
            && $this->config->isInstantEnabled($this->getStoreId()) === true;
    }

    private function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }

    public function isInstantEnabled()
    {
        return $this->config->isInstantEnabled($this->getStoreId());
    }

    public function makeSeoRequest()
    {
        return $this->config->makeSeoRequest($this->getStoreId());
    }
}
