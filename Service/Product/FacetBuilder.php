<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Customer\Api\GroupExcludedWebsiteRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
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
        protected GroupCollection                         $groupCollection,
        protected GroupExcludedWebsiteRepositoryInterface $groupExcludedWebsiteRepository,
    )
    {}

    /**
     * Return the configuration to be used for the store product index `attributesForFaceting`
     * @param int $storeId - The store ID for the index to be configured
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
            $this->addMerchandisingFacets($storeId, $this->getRawFacets($storeId))
        );
    }

    /**
     * Return the configuration to be used for the store product index `renderingContent`
     * @param int $storeId - The store ID for the index to be configured
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
        $attributes = $this->getRenderingContentAttributes($this->extractFacetAttributeNames($facets));
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
     * For an array of facet data, return an array of attribute names only
     *
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
     * Format the facet data to be : renderingContent > facetOrdering > values
     * @param string[] $attributes
     * @return array<string, array> - Array key is the attribute name and the value is an object containing a `sortRemainingBy` value
     */
    protected function getRenderingContentValues(array $attributes): array
    {
        return array_combine(
            $attributes,
            array_fill(0, count($attributes), [ 'sortRemainingBy' => 'alpha' ])
        );
    }

    /**
     * Take raw facet (common) attributes and convert to include attributes specifically needed for `renderingContent`
     * @param string[] $facets
     * @return string[]
     */
    protected function getRenderingContentAttributes(array $facets): array
    {
        return array_map(
            function(string $attribute) {
                if ($attribute === self::FACET_ATTRIBUTE_CATEGORIES) {
                    $attribute = $this->getCategoryAttributeNameForRenderingContent();
                }
                return $attribute;
            },
            $facets
        );
    }

    /**
     * `renderingContent` cannot utilize the entire categories object but instead must reference a scalar value
     * Obtaining the root level of the category data will enable it to become selectable in the Algolia Dashboard
     * for "Facet Display" and "Order facets" within merchandising rules
     *
     * @return string
     */
    protected function getCategoryAttributeNameForRenderingContent(): string
    {
        return self::FACET_ATTRIBUTE_CATEGORIES . '.level0';
    }

    /**
     * Return an associative array for an attribute that mimics the minimum structure used by the Magento configuration
     *
     * @param string $attribute
     * @param bool $searchable
     * @return array{attribute: string, searchable: string}
     */
    protected function getRawFacet(string $attribute, bool $searchable = false): array
    {
        return [
            self::FACET_KEY_ATTRIBUTE_NAME => $attribute,
            self::FACET_KEY_SEARCHABLE => $searchable ? self::FACET_SEARCHABLE_SEARCHABLE : self::FACET_SEARCHABLE_NOT_SEARCHABLE,
        ];
    }

    /**
     * Generates common data to be used for both `attributesForFaceting` and `renderingContent`
     *
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

        $this->facets[$storeId] = $this->assertCategoryFacet($storeId, $rawFacets);

        return $this->facets[$storeId];
    }

    /**
     * Does a given array of facets include a category facet?
     *
     * @param array<array<string, mixed>> $facets
     * @return bool
     */
    protected function hasCategoryFacet(array $facets): bool
    {
        return !!array_filter($facets, function($facet) {
            return $facet['attribute'] === self::FACET_ATTRIBUTE_CATEGORIES;
        });
    }

    /**
     * Applies the category facet if not manually configured but necessary for category functionality
     * (The presence of the category facet drives logic for `attributesForFaceting` and `renderingContent`)
     *
     * @param int $storeId
     * @param array<array<string, mixed>> $facets
     * @return array<array<string, mixed>>
     */
    protected function assertCategoryFacet(int $storeId, array $facets): array
    {
        if ($this->configHelper->replaceCategories($storeId)
            && !$this->hasCategoryFacet($facets)
        ) {
            $facets[] = $this->getRawFacet(self::FACET_ATTRIBUTE_CATEGORIES);
        }

        return $facets;
    }

    /**
     * Add merchandising facets as needed for `attributesForFaceting`
     *
     * @param int $storeId
     * @param array<array<string, mixed>> $facets
     * @return array|string[]
     */
    protected function addMerchandisingFacets(int $storeId, array $facets): array
    {
        if ($this->hasCategoryFacet($facets)) {
            $facets[] = $this->getRawFacet($this->getCategoryAttributeNameForRenderingContent());
        }

        // Used for legacy merchandising features - always required!
        $facets[] = $this->getRawFacet(self::FACET_ATTRIBUTES_CATEGORY_ID);

        if ($this->configHelper->isVisualMerchEnabled($storeId)) {
            // Must be searchable per https://www.algolia.com/doc/guides/solutions/ecommerce/browse/tutorials/category-pages/#configure-your-index
            $facets[] = $this->getRawFacet($this->configHelper->getCategoryPageIdAttributeName($storeId), true);
        }

        return $facets;
    }

    /**
     * Get an array of pricing attribute names based on currency and customer group configuration
     *
     * @param int $storeId
     * @return string[]
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function getPricingAttributes(int $storeId): array
    {
        $pricingAttributes = [];
        $currencies = $this->configHelper->getAllowedCurrencies($storeId);
        foreach ($currencies as $currencyCode) {
            $pricingAttributes[] = self::FACET_ATTRIBUTE_PRICE . '.' . $currencyCode . '.default';
            $pricingAttributes = array_merge($pricingAttributes, $this->getGroupPricingAttributes($storeId, $currencyCode));
        }

        return $pricingAttributes;
    }

    /**
     * Get an array of pricing attribute names based on customer group configuration
     *
     * @param int $storeId
     * @param string $currencyCode
     * @return string[]
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function getGroupPricingAttributes(int $storeId, string $currencyCode): array
    {
        $groupPricingAttributes = [];
        if ($this->configHelper->isCustomerGroupsEnabled($storeId)) {
            $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();
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
     * Format the `attributesForFaceting` values based on modifiers defined at:
     * https://www.algolia.com/doc/api-reference/api-parameters/attributesForFaceting/#modifiers
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
