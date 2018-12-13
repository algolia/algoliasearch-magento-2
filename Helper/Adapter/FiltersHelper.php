<?php

namespace Algolia\AlgoliaSearch\Helper\Adapter;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\Product\AttributeHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Registry;

class FiltersHelper
{
    /** @var ConfigHelper */
    private $config;

    /** @var Registry */
    private $registry;

    /** @var CustomerSession */
    private $customerSession;

    /** @var AttributeHelper */
    private $attributeHelper;

    /** @var Http */
    private $request;

    /**
     * @param ConfigHelper $config
     * @param Registry $registry
     * @param CustomerSession $customerSession
     * @param AttributeHelper $attributeHelper
     * @param Http $request
     */
    public function __construct(
        ConfigHelper $config,
        Registry $registry,
        CustomerSession $customerSession,
        AttributeHelper $attributeHelper,
        Http $request
    ) {
        $this->config = $config;
        $this->registry = $registry;
        $this->customerSession = $customerSession;
        $this->attributeHelper = $attributeHelper;
        $this->request = $request;
    }

    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get the pagination filters from the url
     *
     * @return array
     */
    public function getPaginationFilters()
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
    public function getCategoryFilters()
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
    public function getFacetFilters($storeId)
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
    public function getPriceFilters($storeId)
    {
        $priceFilters = [];

        // Handle price filtering
        $currencyCode = $this->config->getCurrencyCode($storeId);
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
}
