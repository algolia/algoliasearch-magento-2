<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Magento\Customer\Api\GroupExcludedWebsiteRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class SortingTransformer
{
    public const REPLICA_TRANSFORM_MODE_STANDARD = 1;
    public const REPLICA_TRANSFORM_MODE_VIRTUAL = 2;
    public const REPLICA_TRANSFORM_MODE_ACTUAL = 3;

    /**
     * @var array<int,<array<string, mixed>>>
     */
    protected array $_sortingIndices = [];

    public function __construct(
        protected ConfigHelper                            $configHelper,
        protected StoreManagerInterface                   $storeManager,
        protected IndexNameFetcher                        $indexNameFetcher,
        protected GroupCollection                                      $groupCollection,
        protected GroupExcludedWebsiteRepositoryInterface $groupExcludedWebsiteRepository
    )
    {}

    /**
     * Augment sorting configuration with corresponding replica indices, ranking,
     * and (as needed) customer group pricing
     *
     * @param ?int $storeId
     * @param ?int $currentCustomerGroupId
     * @param ?array $attrs - serialized array of sorting attributes to transform (defaults to saved sorting config)
     * @param bool $clearCache - If set to true will update the cache
     * @return array of transformed sorting / replica objects
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getSortingIndices(
        ?int   $storeId = null,
        ?int   $currentCustomerGroupId = null,
        ?array $attrs = null,
        bool   $clearCache = false
    ): array
    {
        // Selectively cache this result - only cache manipulation of saved settings per store
        $useCache = is_null($currentCustomerGroupId) && is_null($attrs);

        if ($clearCache) {
            unset($this->_sortingIndices[$storeId]);
        }

        if ($useCache
            && array_key_exists($storeId, $this->_sortingIndices)
            && is_array($this->_sortingIndices[$storeId])) {
            return $this->_sortingIndices[$storeId];
        }

        // If no sorting configuration is supplied - obtain from the saved configuration
        if (!$attrs) {
            $attrs = $this->configHelper->getSorting($storeId);
        }

        $primaryIndexName = $this->indexNameFetcher->getProductIndexName($storeId);
        $currency = $this->configHelper->getCurrencyCode($storeId);
        $attributesToAdd = [];
        foreach ($attrs as $key => $attr) {
            $indexName = false;
            $sortAttribute = false;
            // Group pricing
            if ($this->configHelper->isCustomerGroupsEnabled($storeId) && $attr[ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME] === ReplicaManagerInterface::SORT_ATTRIBUTE_PRICE) {
                $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
                $groupCollection = $this->groupCollection;
                if (!is_null($currentCustomerGroupId)) {
                    $groupCollection->addFilter('customer_group_id', $currentCustomerGroupId);
                }
                foreach ($groupCollection as $group) {
                    $customerGroupId = (int) $group->getData('customer_group_id');
                    if (!$this->isGroupPricingExcludedFromWebsite($customerGroupId, $websiteId)) {
                        $newAttr = $this->getCustomerGroupSortPriceOverride($primaryIndexName, $customerGroupId, $currency, $attr);
                        $attributesToAdd[$newAttr['sort']][] = $this->decorateSortAttribute($newAttr);
                    }
                }
                // Regular pricing
            } elseif ($attr[ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME] === ReplicaManagerInterface::SORT_ATTRIBUTE_PRICE) {
                $indexName = $primaryIndexName . '_' . $attr['attribute'] . '_' . 'default' . '_' . $attr['sort'];
                $sortAttribute = $attr['attribute'] . '.' . $currency . '.' . 'default';
                // All other sort attributes
            } else {
                $indexName = $primaryIndexName . '_' . $attr['attribute'] . '_' . $attr['sort'];
                $sortAttribute = $attr['attribute'];
            }

            // Decorate all non group pricing attributes
            if ($indexName && $sortAttribute) {
                $attrs[$key]['name'] = $indexName;
                $attrs[$key]['ranking'] = $this->getSortAttributingRankingSetting($sortAttribute, $attr['sort']);
                $attrs[$key] = $this->decorateSortAttribute($attrs[$key]);
            }
        }
        $attrsToReturn = [];

        foreach ($attrs as $attr) {
            if ($attr[ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME] == ReplicaManagerInterface::SORT_ATTRIBUTE_PRICE
                && count($attributesToAdd)
                && isset($attributesToAdd[$attr['sort']])) {
                $attrsToReturn = array_merge($attrsToReturn, $attributesToAdd[$attr['sort']]);
            } else {
                $attrsToReturn[] = $attr;
            }
        }

        if ($useCache) {
            $this->_sortingIndices[$storeId] = $attrsToReturn;
        }

        return $attrsToReturn;
    }

    /**
     * @throws LocalizedException
     */
    protected function isGroupPricingExcludedFromWebsite(int $customerGroupId, int $websiteId): bool
    {
        $excludedWebsites = $this->groupExcludedWebsiteRepository->getCustomerGroupExcludedWebsites($customerGroupId);
        return in_array($websiteId, $excludedWebsites);
    }


    /**
     * When group pricing is enabled a replica must be created for each possible sort
     *
     * @param string $originalIndexName
     * @param int $customerGroupId
     * @param string $currency
     * @param array $origAttr
     * @return array
     */
    protected function getCustomerGroupSortPriceOverride(
        string $originalIndexName,
        int    $customerGroupId,
        string $currency,
        array  $origAttr): array
    {
        $attrName = $origAttr['attribute'];
        $sortDir = $origAttr['sort'];
        $groupIndexNameSuffix = 'group_' . $customerGroupId;
        $groupIndexName = $originalIndexName . '_' . $attrName . '_' . $groupIndexNameSuffix . '_' . $sortDir;

        $newAttr = array_merge(
            $origAttr,
            ['name' => $groupIndexName]
        );

        $groupSortAttribute = $attrName . '.' . $currency . '.' . $groupIndexNameSuffix;
        $newAttr['ranking'] = $this->getSortAttributingRankingSetting($groupSortAttribute, $sortDir);
        return $this->decorateSortAttribute($newAttr);
    }

    /*
     * Add data to the sort attribute object
     */
    protected function decorateSortAttribute(array $attr): array
    {
        if (!array_key_exists('label', $attr) && array_key_exists('sortLabel', $attr)) {
            $attr['label'] = $attr['sortLabel'];
        }
        return $attr;
    }

    /**
     * Get ranking setting to be used for the standard sorting replica
     * @param string $attrName
     * @param string $sortDir
     * @return string[]
     */
    protected function getSortAttributingRankingSetting(string $attrName, string $sortDir): array
    {
        return [
            $sortDir . '(' . $attrName . ')',
            'typo',
            'geo',
            'words',
            'filters',
            'proximity',
            'attribute',
            'exact',
            'custom',
        ];
    }

    /**
     * @param array $sortingIndices - array of sortingIndices objects
     * @param int $mode Use REPLICA_TRANSFORM_MODE_ constant - defaults to _ACTUAL which will give the configuration defined in the admin panel
     * @return string[]
     */
    public function transformSortingIndicesToReplicaSetting(
        array $sortingIndices,
        int   $mode = self::REPLICA_TRANSFORM_MODE_ACTUAL
    ): array
    {
        return array_map(
            function ($sort) use ($mode) {
                $replica = $sort['name'];
                if (
                    $mode === self::REPLICA_TRANSFORM_MODE_VIRTUAL
                    || !empty($sort[ReplicaManagerInterface::SORT_KEY_VIRTUAL_REPLICA]) && $mode === self::REPLICA_TRANSFORM_MODE_ACTUAL
                ) {
                    $replica = "virtual($replica)";
                }
                return $replica;
            },
            $sortingIndices
        );
    }
}
