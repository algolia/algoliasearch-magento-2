<?php

namespace Algolia\AlgoliaSearch\Helper\Configuration;

use Algolia\AlgoliaSearch\Service\Serializer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;

class InstantSearchHelper
{
    // General config
    public const IS_ENABLED = 'algoliasearch_instant/instant/is_instant_enabled';
    public const DOM_SELECTOR = 'algoliasearch_instant/instant/instant_selector';
    public const NUMBER_OF_PRODUCT_RESULTS = 'algoliasearch_instant/instant/number_product_results';
    public const REPLACE_CATEGORIES = 'algoliasearch_instant/instant/replace_categories';

    // Facets
    public const FACETS = 'algoliasearch_instant/instant_facets/facets';
    public const MAX_VALUES_PER_FACET = 'algoliasearch_instant/instant_facets/max_values_per_facet';
    public const IS_DYNAMIC_FACETS_ENABLED = 'algoliasearch_instant/instant_facets/enable_dynamic_facets';

    // Sorts
    public const SORTING_INDICES = 'algoliasearch_instant/instant_sorts/sorts';

    // Display options
    public const SHOW_SUGGESTIONS_NO_RESULTS = 'algoliasearch_instant/instant_options/show_suggestions_on_no_result_page';
    public const IS_SEARCHBOX_ENABLED = 'algoliasearch_instant/instant_options/instantsearch_searchbox';
    public const IS_ADD_TO_CART_ENABLED = 'algoliasearch_instant/instant_options/add_to_cart_enable';
    public const IS_INFINITE_SCROLL_ENABLED = 'algoliasearch_instant/instant_options/infinite_scroll_enable';
    public const HIDE_PAGINATION = 'algoliasearch_instant/instant_options/hide_pagination';

    // Redirects
    public const IS_REDIRECT_ENABLED = 'algoliasearch_instant/instant_redirects/enable';
    public const REDIRECT_OPTIONS = 'algoliasearch_instant/instant_redirects/options';

    public function __construct(
        protected ScopeConfigInterface $configInterface,
        protected WriterInterface      $configWriter,
        protected Serializer           $serializer
    ) {}

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getDomSelector(?int $storeId = null): string
    {
        return (string) $this->configInterface->getValue(self::DOM_SELECTOR, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getNumberOfProductResults(?int $storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::NUMBER_OF_PRODUCT_RESULTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function shouldReplaceCategories(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::REPLACE_CATEGORIES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getFacets(?int $storeId = null): array
    {
        $attrs = $this->serializer->unserialize($this->configInterface->getValue(
            self::FACETS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        if ($attrs) {
            foreach ($attrs as &$attr) {
                if ($attr['type'] === 'other') {
                    $attr['type'] = $attr['other_type'];
                }
            }
            if (is_array($attrs)) {
                return array_values($attrs);
            }
        }
        return [];
    }
    public function getMaxValuesPerFacet(?int $storeId = null): int
    {
        return (int) $this->configInterface->getValue(
            self::MAX_VALUES_PER_FACET,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isDynamicFacetsEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(
            self::IS_DYNAMIC_FACETS_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /***
     * @return array<string,<array<string, mixed>>>
     */
    public function getSorting(?int $storeId = null): array
    {
        return $this->serializer->unserialize($this->getRawSortingValue($storeId));
    }

    public function getRawSortingValue(?int $storeId = null): string
    {
        return (string) $this->configInterface->getValue(
            self::SORTING_INDICES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function setSorting(
        array $sorting,
        string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        ?int $scopeId = null
    ): void
    {
        $this->configWriter->save(
            self::SORTING_INDICES,
            $this->serializer->serialize($sorting),
            $scope,
            $scopeId
        );
    }

    public function isSearchBoxEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && $this->configInterface->isSetFlag(self::IS_SEARCHBOX_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isAddToCartEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(
            self::IS_ADD_TO_CART_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isInfiniteScrollEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && $this->configInterface->isSetFlag(
                self::IS_INFINITE_SCROLL_ENABLED,
                ScopeInterface::SCOPE_STORE,
                $storeId);
    }

    public function shouldHidePagination(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && $this->configInterface->isSetFlag(self::HIDE_PAGINATION, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isInstantRedirectEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->isSetFlag(self::IS_REDIRECT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getInstantRedirectOptions(?int $storeId = null): array
    {
        $value = $this->configInterface->getValue(self::REDIRECT_OPTIONS, ScopeInterface::SCOPE_STORE, $storeId);
        return empty($value) ? [] : explode(',', (string) $value);
    }
}
