<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Exception\ProductReindexingException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\Product\RecordBuilder as ProductRecordBuilder;
use Magento\Bundle\Model\Product\Type as BundleProductType;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Api\GroupExcludedWebsiteRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Directory\Model\Currency as CurrencyHelper;
use Magento\Eav\Model\Config;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class ProductHelper extends AbstractEntityHelper
{
    use EntityHelperTrait;
    public const INDEX_NAME_SUFFIX = '_products';
    /**
     * @var AbstractType[]
     */
    protected ?array $compositeTypes = null;

    /**
     * @var array<string, string>
     */
    protected array $productAttributes;

    /**
     * @var string[]
     */
    protected array $predefinedProductAttributes = [
        'name',
        'url_key',
        'image',
        'small_image',
        'thumbnail',
        'msrp_enabled', // Needed to handle MSRP behavior
    ];

    /**
     * @var string[]
     */
    protected array $createdAttributes = [
        'path',
        'categories',
        'categories_without_path',
        'ordered_qty',
        'total_ordered',
        'stock_qty',
        'rating_summary',
        'media_gallery',
        'in_stock',
        'default_bundle_options',
    ];

    public function __construct(
        protected Config                                  $eavConfig,
        protected ConfigHelper                            $configHelper,
        protected AlgoliaHelper                           $algoliaHelper,
        protected DiagnosticsLogger                       $logger,
        protected StoreManagerInterface                   $storeManager,
        protected ManagerInterface                        $eventManager,
        protected Visibility                              $visibility,
        protected Stock                                   $stockHelper,
        protected CurrencyHelper                          $currencyManager,
        protected Type                                    $productType,
        protected CollectionFactory                       $productCollectionFactory,
        protected GroupCollection                         $groupCollection,
        protected GroupExcludedWebsiteRepositoryInterface $groupExcludedWebsiteRepository,
        protected IndexNameFetcher                        $indexNameFetcher,
        protected ReplicaManagerInterface                 $replicaManager,
        protected ProductInterfaceFactory                 $productFactory,
        protected ProductRecordBuilder                    $productRecordBuilder,
    )
    {
        parent::__construct($indexNameFetcher);
    }

    /**
     * @param bool $addEmptyRow
     * @return array
     * @throws LocalizedException
     */
    public function getAllAttributes(bool $addEmptyRow = false): array
    {
        if (!isset($this->productAttributes)) {
            $this->productAttributes = [];

            $allAttributes = $this->eavConfig->getEntityAttributeCodes('catalog_product');

            $productAttributes = array_merge([
                'name',
                'path',
                'categories',
                'categories_without_path',
                'description',
                'ordered_qty',
                'total_ordered',
                'stock_qty',
                'rating_summary',
                'media_gallery',
                'in_stock',
            ], $allAttributes);

            $excludedAttributes = [
                'all_children', 'available_sort_by', 'children', 'children_count', 'custom_apply_to_products',
                'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update',
                'custom_use_parent_settings', 'default_sort_by', 'display_mode', 'filter_price_range',
                'global_position', 'image', 'include_in_menu', 'is_active', 'is_always_include_in_menu', 'is_anchor',
                'landing_page', 'lower_cms_block', 'page_layout', 'path_in_store', 'position', 'small_image',
                'thumbnail', 'url_key', 'url_path', 'visible_in_menu', 'quantity_and_stock_status',
            ];

            $productAttributes = array_diff($productAttributes, $excludedAttributes);

            foreach ($productAttributes as $attributeCode) {
                $this->productAttributes[$attributeCode] = $this->eavConfig
                    ->getAttribute('catalog_product', $attributeCode)
                    ->getFrontendLabel();
            }
        }

        $attributes = $this->productAttributes;

        if ($addEmptyRow === true) {
            $attributes[''] = '';
        }

        uksort($attributes, function ($a, $b) {
            return strcmp($a, $b);
        });

        return $attributes;
    }

    /**
     * @param $additionalAttributes
     * @param $attributeName
     * @return bool
     */
    public function isAttributeEnabled($additionalAttributes, $attributeName): bool
    {
        return $this->productRecordBuilder->isAttributeEnabled($additionalAttributes, $attributeName);
    }

    /**
     * @param int $storeId
     * @param string[]|null $productIds
     * @param bool $onlyVisible
     * @param bool $includeNotVisibleIndividually
     * @return ProductCollection
     */
    public function getProductCollectionQuery(
        int $storeId,
        ?array $productIds = null,
        bool $onlyVisible = true,
        bool $includeNotVisibleIndividually = false
    ): ProductCollection
    {
        $this->logger->startProfiling(__METHOD__);
        $productCollection = $this->productCollectionFactory->create();
        $products = $productCollection
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->distinct(true);

        if ($onlyVisible) {
            $products = $products->addAttributeToFilter('status', ['=' => Status::STATUS_ENABLED]);

            if ($includeNotVisibleIndividually === false) {
                $products = $products
                    ->addAttributeToFilter('visibility', ['in' => $this->visibility->getVisibleInSiteIds()]);
            }

            $this->addStockFilter($products, $storeId);
        }

        $this->addMandatoryAttributes($products);

        $additionalAttr = $this->getAdditionalAttributes($storeId);

        foreach ($additionalAttr as &$attr) {
            $attr = $attr['attribute'];
        }

        $attrs = array_merge($this->predefinedProductAttributes, $additionalAttr);
        $attrs = array_diff($attrs, $this->createdAttributes);

        $products = $products->addAttributeToSelect(array_values($attrs));

        if ($productIds && count($productIds) > 0) {
            $products = $products->addAttributeToFilter('entity_id', ['in' => $productIds]);
        }

        // Only for backward compatibility
        $this->eventManager->dispatch(
            'algolia_rebuild_store_product_index_collection_load_before',
            ['store' => $storeId, 'collection' => $products]
        );
        $this->eventManager->dispatch(
            'algolia_after_products_collection_build',
            [
                'store' => $storeId,
                'collection' => $products,
                'only_visible' => $onlyVisible,
                'include_not_visible_individually' => $includeNotVisibleIndividually,
            ]
        );

        $this->logger->stopProfiling(__METHOD__);
        return $products;
    }

    /**
     * @param $products
     * @param $storeId
     * @return void
     */
    protected function addStockFilter($products, $storeId): void
    {
        if ($this->configHelper->getShowOutOfStock($storeId) === false) {
            $this->stockHelper->addInStockFilterToCollection($products);
        }
    }

    /**
     * Adds key attributes like pricing and visibility to product collection query.
     * IMPORTANT: The "Product Price" (aka `catalog_product_price`) index must be
     *            up-to-date in order to properly build this collection.
     *            Otherwise, the resulting inner join will filter out products
     *            without a price. These removed products will initiate a `deleteObject`
     *            operation against the underlying product index in Algolia.
     * @param ProductCollection $products
     * @return void
     */
    protected function addMandatoryAttributes(ProductCollection $products): void
    {
        $products->addFinalPrice()
            ->addAttributeToSelect('special_price')
            ->addAttributeToSelect('special_from_date')
            ->addAttributeToSelect('special_to_date')
            ->addAttributeToSelect('visibility')
            ->addAttributeToSelect('status');
    }

    /**
     * @param int|null $storeId
     * @return array
     */
    protected function getAdditionalAttributes(?int $storeId = null): array
    {
        return $this->productRecordBuilder->getAdditionalAttributes($storeId);
    }

    /**
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getIndexSettings(?int $storeId = null): array
    {
        $searchableAttributes = $this->getSearchableAttributes($storeId);
        $customRanking = $this->getCustomRanking($storeId);
        $unretrievableAttributes = $this->getUnretrieveableAttributes($storeId);
        $attributesForFaceting = $this->getAttributesForFaceting($storeId);

        $indexSettings = [
            'searchableAttributes'    => $searchableAttributes,
            'customRanking'           => $customRanking,
            'unretrievableAttributes' => $unretrievableAttributes,
            'attributesForFaceting'   => $attributesForFaceting,
            'maxValuesPerFacet'       => $this->configHelper->getMaxValuesPerFacet($storeId),
            'removeWordsIfNoResults'  => $this->configHelper->getRemoveWordsIfNoResult($storeId),
        ];

        // Additional index settings from event observer
        $transport = new DataObject($indexSettings);
        // Only for backward compatibility
        $this->eventManager->dispatch(
            'algolia_index_settings_prepare',
            ['store_id' => $storeId, 'index_settings' => $transport]
        );
        $this->eventManager->dispatch(
            'algolia_products_index_before_set_settings',
            [
                'store_id' => $storeId,
                'index_settings' => $transport,
            ]
        );

        $indexSettings = $transport->getData();

        return $indexSettings;
    }

    /**
     * @param string $indexName
     * @param string $indexNameTmp
     * @param int $storeId
     * @param bool $saveToTmpIndicesToo
     * @return void
     * @throws AlgoliaException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function setSettings(string $indexName, string $indexNameTmp, int $storeId, bool $saveToTmpIndicesToo = false): void
    {
        $indexSettings = $this->getIndexSettings($storeId);

        $this->algoliaHelper->setSettings(
            $indexName,
            $indexSettings,
            false,
            true,
            '',
            $storeId
        );
        $this->logger->log('Settings: ' . json_encode($indexSettings));
        if ($saveToTmpIndicesToo) {
            $this->algoliaHelper->setSettings(
                $indexNameTmp,
                $indexSettings,
                false,
                true,
                $indexName,
                $storeId
            );
            $this->logger->log('Pushing the same settings to TMP index as well');
        }

        $this->setFacetsQueryRules($indexName, $storeId);
        $this->algoliaHelper->waitLastTask($storeId);

        if ($saveToTmpIndicesToo) {
            $this->setFacetsQueryRules($indexNameTmp, $storeId);
            $this->algoliaHelper->waitLastTask($storeId);
        }

        $this->replicaManager->syncReplicasToAlgolia($storeId, $indexSettings);

        if ($saveToTmpIndicesToo) {
            try {
                $this->algoliaHelper->copySynonyms($indexName, $indexNameTmp, $storeId);
                $this->algoliaHelper->waitLastTask($storeId);
                $this->logger->log('
                        Copying synonyms from production index to "' . $indexNameTmp . '" to not erase them with the index move.
                    ');
            } catch (AlgoliaException $e) {
                $this->logger->error('Error encountered while copying synonyms: ' . $e->getMessage());
            }

            try {
                $this->algoliaHelper->copyQueryRules($indexName, $indexNameTmp, $storeId);
                $this->algoliaHelper->waitLastTask($storeId);
                $this->logger->log('
                        Copying query rules from production index to "' . $indexNameTmp . '" to not erase them with the index move.
                    ');
            } catch (AlgoliaException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param Product $product
     * @return array|mixed|null
     * @throws \Exception
     */
    public function getObject(Product $product)
    {
        return $this->productRecordBuilder->buildRecord($product);
    }

    /**
     * @param Product $product
     * @return array|\Magento\Catalog\Api\Data\ProductInterface[]|DataObject[]
     */
    protected function getSubProducts(Product $product): array
    {
        $type = $product->getTypeId();

        if (!in_array($type, ['bundle', 'grouped', 'configurable'], true)) {
            return [];
        }

        $this->logger->startProfiling(__METHOD__);

        $storeId = $product->getStoreId();
        $typeInstance = $product->getTypeInstance();

        if ($typeInstance instanceof Configurable) {
            $subProducts = $typeInstance->getUsedProducts($product);
        } elseif ($typeInstance instanceof BundleProductType) {
            $subProducts = $typeInstance->getSelectionsCollection($typeInstance->getOptionsIds($product), $product)->getItems();
        } else { // Grouped product
            $subProducts = $typeInstance->getAssociatedProducts($product);
        }

        /**
         * @var int $index
         * @var Product $subProduct
         */
        foreach ($subProducts as $index => $subProduct) {
            try {
                $this->canProductBeReindexed($subProduct, $storeId, true);
            } catch (ProductReindexingException) {
                unset($subProducts[$index]);
            }
        }

        $this->logger->stopProfiling(__METHOD__);
        return $subProducts;
    }

    /**
     * Returns all parent product IDs, e.g. when simple product is part of configurable or bundle
     *
     * @param array $productIds
     *
     * @return array
     */
    public function getParentProductIds(array $productIds): array
    {
        $this->logger->startProfiling(__METHOD__);
        $parentIds = [];
        foreach ($this->getCompositeTypes() as $typeInstance) {
            $parentIds = array_merge($parentIds, $typeInstance->getParentIdsByChild($productIds));
        }

        $this->logger->stopProfiling(__METHOD__);
        return $parentIds;
    }

    /**
     * Returns composite product type instances
     *
     * @return AbstractType[]
     *
     * @see \Magento\Catalog\Model\Indexer\Product\Flat\AbstractAction::_getProductTypeInstances
     */
    protected function getCompositeTypes(): array
    {
        if ($this->compositeTypes === null) {
            $productEmulator = $this->productFactory->create();
            foreach ($this->productType->getCompositeTypes() as $typeId) {
                $productEmulator->setTypeId($typeId);
                $this->compositeTypes[$typeId] = $this->productType->factory($productEmulator);
            }
        }

        return $this->compositeTypes;
    }

    /**
     * @param $defaultData
     * @param $customData
     * @param Product $product
     * @return mixed
     */
    protected function addInStock($defaultData, $customData, Product $product)
    {
        return $this->productRecordBuilder->addInStock($defaultData, $customData, $product);
    }

    /**
     * @param $storeId
     * @return array
     */
    protected function getSearchableAttributes($storeId = null)
    {
        $searchableAttributes = [];

        foreach ($this->getAdditionalAttributes($storeId) as $attribute) {
            if ($attribute['searchable'] === '1') {
                if (!isset($attribute['order']) || $attribute['order'] === 'ordered') {
                    $searchableAttributes[] = $attribute['attribute'];
                } else {
                    $searchableAttributes[] = 'unordered(' . $attribute['attribute'] . ')';
                }

                if ($attribute['attribute'] === 'categories') {
                    $searchableAttributes[] = (isset($attribute['order']) && $attribute['order'] === 'ordered') ?
                        'categories_without_path' : 'unordered(categories_without_path)';
                }
            }
        }

        $searchableAttributes = array_values(array_unique($searchableAttributes));

        return $searchableAttributes;
    }

    /**
     * @param $storeId
     * @return array
     */
    protected function getCustomRanking($storeId): array
    {
        $customRanking = [];

        $customRankings = $this->configHelper->getProductCustomRanking($storeId);
        foreach ($customRankings as $ranking) {
            $customRanking[] = $ranking['order'] . '(' . $ranking['attribute'] . ')';
        }

        return $customRanking;
    }

    /**
     * @param $storeId
     * @return array
     */
    protected function getUnretrieveableAttributes($storeId = null)
    {
        $unretrievableAttributes = [];

        foreach ($this->getAdditionalAttributes($storeId) as $attribute) {
            if ($attribute['retrievable'] !== '1') {
                $unretrievableAttributes[] = $attribute['attribute'];
            }
        }

        return $unretrievableAttributes;
    }

    /**
     * @param $storeId
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getAttributesForFaceting($storeId)
    {
        $attributesForFaceting = [];

        $currencies = $this->currencyManager->getConfigAllowCurrencies();

        $facets = $this->configHelper->getFacets($storeId);
        $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        foreach ($facets as $facet) {
            if ($facet['attribute'] === 'price') {
                foreach ($currencies as $currency_code) {
                    $facet['attribute'] = 'price.' . $currency_code . '.default';

                    if ($this->configHelper->isCustomerGroupsEnabled($storeId)) {
                        foreach ($this->groupCollection as $group) {
                            $groupId = (int)$group->getData('customer_group_id');
                            $excludedWebsites = $this->groupExcludedWebsiteRepository->getCustomerGroupExcludedWebsites($groupId);
                            if (in_array($websiteId, $excludedWebsites)) {
                                continue;
                            }
                            $attributesForFaceting[] = 'price.' . $currency_code . '.group_' . $groupId;
                        }
                    }

                    $attributesForFaceting[] = $facet['attribute'];
                }
            } else {
                $attribute = $facet['attribute'];
                if (array_key_exists('searchable', $facet)) {
                    if ($facet['searchable'] === '1') {
                        $attribute = 'searchable(' . $attribute . ')';
                    } elseif ($facet['searchable'] === '3') {
                        $attribute = 'filterOnly(' . $attribute . ')';
                    }
                }

                $attributesForFaceting[] = $attribute;
            }
        }

        if ($this->configHelper->replaceCategories($storeId) && !in_array('categories', $attributesForFaceting, true)) {
            $attributesForFaceting[] = 'categories';
        }

        // Used for merchandising
        $attributesForFaceting[] = 'categoryIds';

        if ($this->configHelper->isVisualMerchEnabled($storeId)) {
            $attributesForFaceting[] = 'searchable(' . $this->configHelper->getCategoryPageIdAttributeName($storeId) . ')';
        }

        return $attributesForFaceting;
    }

    /**
     * @param string $indexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    protected function setFacetsQueryRules(string $indexName, int $storeId = null)
    {
        $this->clearFacetsQueryRules($indexName, $storeId);

        $rules = [];
        $facets = $this->configHelper->getFacets($storeId);
        foreach ($facets as $facet) {
            if (!array_key_exists('create_rule', $facet) || $facet['create_rule'] !== '1') {
                continue;
            }

            $attribute = $facet['attribute'];

            $condition = [
                'anchoring' => 'contains',
                'pattern' => '{facet:' . $attribute . '}',
                'context' => 'magento_filters',
            ];

            $rules[] = [
                AlgoliaConnector::ALGOLIA_API_OBJECT_ID => 'filter_' . $attribute,
                'description' => 'Filter facet "' . $attribute . '"',
                'conditions' => [$condition],
                'consequence' => [
                    'params' => [
                        'automaticFacetFilters' => [$attribute],
                        'query' => [
                            'remove' => ['{facet:' . $attribute . '}'],
                        ],
                    ],
                ],
            ];
        }

        if ($rules) {
            $this->logger->log('Setting facets query rules to "' . $indexName . '" index: ' . json_encode($rules));
            $this->algoliaHelper->saveRules($indexName, $rules, true, $storeId);
        }
    }

    /**
     * @param $indexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    protected function clearFacetsQueryRules($indexName, int $storeId = null): void
    {
        try {
            $hitsPerPage = 100;
            $page = 0;
            do {
                $fetchedQueryRules = $this->algoliaHelper->searchRules(
                    $indexName,
                    [
                        'context' => 'magento_filters',
                        'page' => $page,
                        'hitsPerPage' => $hitsPerPage,
                    ],
                    $storeId
                );

                if (!$fetchedQueryRules || !array_key_exists('hits', $fetchedQueryRules)) {
                    break;
                }

                foreach ($fetchedQueryRules['hits'] as $hit) {
                    $this->algoliaHelper->deleteRule(
                        $indexName,
                        $hit[AlgoliaConnector::ALGOLIA_API_OBJECT_ID],
                        true,
                        $storeId
                    );
                }

                $page++;
            } while (($page * $hitsPerPage) < $fetchedQueryRules['nbHits']);
        } catch (AlgoliaException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    /**
     * Check if product can be index on Algolia
     *
     * @param Product $product
     * @param int $storeId
     * @param bool $isChildProduct
     *
     * @return bool
     */
    public function canProductBeReindexed($product, $storeId, $isChildProduct = false)
    {
        return $this->productRecordBuilder->canProductBeReindexed($product, $storeId, $isChildProduct);
    }

    /**
     * Returns is product in stock
     *
     * @param Product $product
     * @param int $storeId
     *
     * @return bool
     */
    public function productIsInStock($product, $storeId): bool
    {
        return $this->productRecordBuilder->productIsInStock($product, $storeId);
    }

    /**
     * @param $replicas
     * @return array
     * @throws AlgoliaException
     * @deprecated This method has been superseded by `decorateReplicasSetting` and should no longer be used
     */
    public function handleVirtualReplica($replicas): array
    {
        throw new AlgoliaException("This method is no longer supported.");
    }

    /**
     * Return a formatted Algolia `replicas` configuration for the provided sorting indices
     * @param array $sortingIndices Array of sorting index objects
     * @return string[]
     * @deprecated This method should no longer used
     */
    protected function decorateReplicasSetting(array $sortingIndices): array {
        return array_map(
            function($sort) {
                $replica = $sort['name'];
                return !! $sort[ReplicaManagerInterface::SORT_KEY_VIRTUAL_REPLICA]
                    ? "virtual($replica)"
                    : $replica;
            },
            $sortingIndices
        );
    }

    /**
     * Moving to ReplicaManager class
     * @param string $indexName
     * @param int $storeId
     * @param array|bool $sortingAttribute
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Exception
     * @deprecated This function will be removed in a future release
     * @see Algolia::AlgoliaSearch::Api::Product::ReplicaManagerInterface
     */
    public function handlingReplica(string $indexName, int $storeId, array|bool $sortingAttribute = false): void
    {
        $sortingIndices = $this->configHelper->getSortingIndices($indexName, $storeId, null, $sortingAttribute);
        if ($this->configHelper->isInstantEnabled($storeId)) {
            $newReplicas = $this->decorateReplicasSetting($sortingIndices);

            try {
                $currentSettings = $this->algoliaHelper->getSettings($indexName, $storeId);
                if (array_key_exists('replicas', $currentSettings)) {
                    $oldReplicas = $currentSettings['replicas'];
                    $replicasToDelete = array_diff($oldReplicas, $newReplicas);
                    $this->algoliaHelper->setSettings(
                        $indexName,
                        ['replicas' => $newReplicas],
                        false,
                        false,
                        '',
                        $storeId
                    );
                    $setReplicasTaskId = $this->algoliaHelper->getLastTaskId($storeId);
                    $this->algoliaHelper->waitLastTask($storeId, $indexName, $setReplicasTaskId);
                    if (count($replicasToDelete) > 0) {
                        foreach ($replicasToDelete as $deletedReplica) {
                            $this->algoliaHelper->deleteIndex($deletedReplica, $storeId);
                        }
                    }
                }
            } catch (AlgoliaException $e) {
                if ($e->getCode() !== 404) {
                    $this->logger->log($e->getMessage());
                    throw $e;
                }
            }
        }
    }
}
