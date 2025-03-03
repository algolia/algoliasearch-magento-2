<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Customer\Api\GroupExcludedWebsiteRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Directory\Model\Currency as CurrencyHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class FacetBuilder
{
    public const FACET_ATTRIBUTE_PRICE = 'price';

    public function __construct(
        protected ConfigHelper                            $configHelper,
        protected StoreManagerInterface                   $storeManager,
        protected CurrencyHelper                          $currencyManager,
        protected GroupCollection                         $groupCollection,
        protected GroupExcludedWebsiteRepositoryInterface $groupExcludedWebsiteRepository,
    )
    {}

    /**
     * @param int $storeId
     * @return array<string, array>|null
     */
    public function getRenderingContent(int $storeId): ?array
    {
        return null;
    }

    /**
     * @param int $storeId
     * @return string[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAttributesForFaceting(int $storeId): array
    {
        $attributesForFaceting = [];
        $facets = $this->configHelper->getFacets($storeId);
        foreach ($facets as $facet) {
            if ($facet['attribute'] === self::FACET_ATTRIBUTE_PRICE) {
                $attributesForFaceting = array_merge($attributesForFaceting, $this->getPricingAttributesForFaceting($storeId));
            } else {
                $attributesForFaceting[] = $this->decorateAttributeForFaceting($facet);
            }
        }

        return $this->addCategoryAttributes($storeId, $attributesForFaceting);
    }

    protected function getPricingAttributesForFaceting(int $storeId): array
    {
        $pricingAttributes = [];
        $currencies = $this->currencyManager->getConfigAllowCurrencies();
        $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();

        foreach ($currencies as $currency_code) {
            $pricingAttributes[] = 'price.' . $currency_code . '.default';

            if ($this->configHelper->isCustomerGroupsEnabled($storeId)) {
                foreach ($this->groupCollection as $group) {
                    $groupId = (int)$group->getData('customer_group_id');
                    $excludedWebsites = $this->groupExcludedWebsiteRepository->getCustomerGroupExcludedWebsites($groupId);
                    if (in_array($websiteId, $excludedWebsites)) {
                        continue;
                    }
                    $pricingAttributes[] = 'price.' . $currency_code . '.group_' . $groupId;
                }
            }
        }

        return $pricingAttributes;
    }


    protected function decorateAttributeForFaceting($facet): string {
        $attribute = $facet['attribute'];
        if (array_key_exists('searchable', $facet)) {
            if ($facet['searchable'] === '1') {
                $attribute = 'searchable(' . $attribute . ')';
            } elseif ($facet['searchable'] === '3') {
                $attribute = 'filterOnly(' . $attribute . ')';
            }
        }
        return $attribute;
    }

    protected function addCategoryAttributes(int $storeId, array $attributesForFaceting): array
    {
        if ($this->configHelper->replaceCategories($storeId) && !in_array('categories', $attributesForFaceting, true)) {
            $attributesForFaceting[] = 'categories';
        }

        // Added for legacy merchandising features
        $attributesForFaceting[] = 'categoryIds';

        if ($this->configHelper->isVisualMerchEnabled($storeId)) {
            $attributesForFaceting[] = 'searchable(' . $this->configHelper->getCategoryPageIdAttributeName($storeId) . ')';
        }

        return $attributesForFaceting;
    }

}
