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
    public const FACET_ATTRIBUTE_CATEGORIES = 'categories';
    public const FACET_ATTRIBUTES_CATEGORY_ID = 'categoryIds';

    public const FACET_KEY_ATTRIBUTE_NAME = 'attribute';
    public const FACET_KEY_SEARCHABLE = 'searchable';

    public const FACET_SEARCHABLE_SEARCHABLE = '1';
    public const FACET_SEARCHABLE_NOT_SEARCHABLE = '2';
    public const FACET_SEARCHABLE_FILTER_ONLY = '3';

    // Local raw facet cache, indexed by $storeId
    protected array $facets = [];

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
     * @return string[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAttributesForFaceting(int $storeId): array
    {
        return array_map(
            function($facet) {
                return $this->decorateAttributeForFaceting($facet);
            },
            $this->getRawFacets($storeId)
        );
    }

    /**
     * @param int $storeId
     * @return array<string, array>|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getRenderingContent(int $storeId): ?array
    {
        $facets = $this->getRawFacets($storeId);
        if (empty($facets)) {
            return null;
        }
        $attributes = $this->extractFacetAttributeNames($facets);
        return [
            'facetOrdering' => [
                'facets' => [
                    'order' => $attributes
                ],
                'values' => $this->getRenderingContentValues($attributes)
            ],
        ];
    }

    /**
     * @param array<array<string, mixed>> $facets
     * @return string[]
     */
    protected function extractFacetAttributeNames(array $facets): array
    {
        return array_map(
            function($facet) {
                return $facet[self::FACET_KEY_ATTRIBUTE_NAME];
            },
            $facets
        );
    }

    /**
     * @param string[] $facets
     * @return array<string, array>
     */
    protected function getRenderingContentValues(array $facets): array
    {
        return array_combine(
            array_map(
                function(string $attribute) {
                    if ($attribute === self::FACET_ATTRIBUTE_CATEGORIES) {
                        $attribute = self::FACET_ATTRIBUTE_CATEGORIES . '.level0';
                    }
                    return $attribute;
                },
                $facets
            ),
            array_fill(0, count($facets), [ 'sortRemainingBy' => 'alpha' ])
        );
    }

    /**
     * @param string $attribute
     * @param bool $searchable
     * @return array<string, string>
     */
    protected function getRawFacet(string $attribute, bool $searchable = false): array
    {
        return [
            self::FACET_KEY_ATTRIBUTE_NAME => $attribute,
            self::FACET_KEY_SEARCHABLE => $searchable ? self::FACET_SEARCHABLE_SEARCHABLE : self::FACET_SEARCHABLE_NOT_SEARCHABLE,
        ];
    }

    /**
     * Generates common data to be used for both attributesForFaceting and renderingContent
     * @return array<array<string, mixed>>
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function getRawFacets(int $storeId): array
    {
        if (isset($this->facets[$storeId])) {
            return $this->facets[$storeId];
        }

        $rawFacets = [];
        $configFacets = $this->configHelper->getFacets($storeId);
        foreach ($configFacets as $configFacet) {
            if ($configFacet[self::FACET_KEY_ATTRIBUTE_NAME] === self::FACET_ATTRIBUTE_PRICE) {
                $rawFacets = array_merge($rawFacets, array_map(
                    function(string $attribute) {
                        return $this->getRawFacet($attribute);
                    },
                    $this->getPricingAttributes($storeId)
                ));
            } else {
                $rawFacets[] = $configFacet;
            }
        }

        $this->facets[$storeId] = $this->addCategoryFacets($storeId, $rawFacets);
        return $this->facets[$storeId];
    }

    /**
     * @param int $storeId
     * @param array<array<string, mixed>> $facets
     * @return array<array<string, mixed>>
     */
    protected function addCategoryFacets(int $storeId, array $facets): array
    {
        if ($this->configHelper->replaceCategories($storeId)
            && !array_filter($facets, function($facet) {
                return $facet['attribute'] === self::FACET_ATTRIBUTE_CATEGORIES;
            })
        ) {
            $facets[] = $this->getRawFacet(self::FACET_ATTRIBUTE_CATEGORIES);
        }

        // Added for legacy merchandising features
        $facets[] = $this->getRawFacet(self::FACET_ATTRIBUTES_CATEGORY_ID);

        if ($this->configHelper->isVisualMerchEnabled($storeId)) {
            // Must be searchable per https://www.algolia.com/doc/guides/solutions/ecommerce/browse/tutorials/category-pages/#configure-your-index
            $facets[] = $this->getRawFacet($this->configHelper->getCategoryPageIdAttributeName($storeId), true);
        }

        return $facets;
    }

    /**
     * @param int $storeId
     * @return string[]
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function getPricingAttributes(int $storeId): array
    {
        $pricingAttributes = [];
        $currencies = $this->currencyManager->getConfigAllowCurrencies();
        foreach ($currencies as $currencyCode) {
            $pricingAttributes[] = self::FACET_ATTRIBUTE_PRICE . '.' . $currencyCode . '.default';
            $pricingAttributes = array_merge($pricingAttributes, $this->getGroupPricingAttributes($storeId, $currencyCode));
        }

        return $pricingAttributes;
    }

    /**
     * @param int $storeId
     * @param string $currencyCode
     * @return string[]
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function getGroupPricingAttributes(int $storeId, string $currencyCode): array
    {
        $groupPricingAttributes = [];
        $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();

        if ($this->configHelper->isCustomerGroupsEnabled($storeId)) {
            foreach ($this->groupCollection as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                $excludedWebsites = $this->groupExcludedWebsiteRepository->getCustomerGroupExcludedWebsites($groupId);
                if (in_array($websiteId, $excludedWebsites)) {
                    continue;
                }
                $groupPricingAttributes[] = self::FACET_ATTRIBUTE_PRICE . '.' . $currencyCode . '.group_' . $groupId;
            }
        }
        return $groupPricingAttributes;
    }


    /**
     * @param array<string, string|int> $facet
     * @return string
     */
    protected function decorateAttributeForFaceting(array $facet): string
    {
        $attribute = $facet[self::FACET_KEY_ATTRIBUTE_NAME];
        if (array_key_exists(self::FACET_KEY_SEARCHABLE, $facet)) {
            if ($facet[self::FACET_KEY_SEARCHABLE] == self::FACET_SEARCHABLE_SEARCHABLE) {
                $attribute = 'searchable(' . $attribute . ')';
            } elseif ($facet[self::FACET_KEY_SEARCHABLE] == self::FACET_SEARCHABLE_FILTER_ONLY) {
                $attribute = 'filterOnly(' . $attribute . ')';
            }
        }
        return $attribute;
    }

}
