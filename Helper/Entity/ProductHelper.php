<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Exception\ProductDeletedException;
use Algolia\AlgoliaSearch\Exception\ProductDisabledException;
use Algolia\AlgoliaSearch\Exception\ProductNotVisibleException;
use Algolia\AlgoliaSearch\Exception\ProductOutOfStockException;
use Algolia\AlgoliaSearch\Exception\ProductReindexingException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager;
use Algolia\AlgoliaSearch\Helper\Image as ImageHelper;
use Algolia\AlgoliaSearch\Helper\Logger;
use Algolia\AlgoliaSearch\SearchIndex;
use Magento\Bundle\Model\Product\Type as BundleProductType;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as AttributeResource;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Helper\Stock;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Directory\Model\Currency as CurrencyHelper;
use Magento\Eav\Model\Config;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class ProductHelper
{
    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;
    /**
     * @var GroupCollection
     */
    protected $groupCollection;
    /**
     * @var Config
     */
    protected $eavConfig;
    /**
     * @var ConfigHelper
     */
    protected $configHelper;
    /**
     * @var AlgoliaHelper
     */
    protected $algoliaHelper;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var ManagerInterface
     */
    protected $eventManager;
    /**
     * @var Visibility
     */
    protected $visibility;
    /**
     * @var Stock
     */
    protected $stockHelper;
    /**
     * @var StockRegistryInterface
     */
    protected $stockRegistry;
    /**
     * @var CurrencyHelper
     */
    protected $currencyManager;
    /**
     * @var CategoryHelper
     */
    protected $categoryHelper;
    /**
     * @var PriceManager
     */
    protected $priceManager;
    /**
     * @var ImageHelper
     */
    protected $imageHelper;

    /**
     * @var Type
     */
    protected $productType;

    /**
     * @var AbstractType[]
     */
    protected $compositeTypes;

    /**
     * @var
     */
    protected $productAttributes;

    /**
     * @var string[]
     */
    protected $predefinedProductAttributes = [
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
    protected $createdAttributes = [
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

    /**
     * @var string[]
     */
    protected $attributesToIndexAsArray = [
        'sku',
        'color',
    ];

    /**
     * ProductHelper constructor.
     *
     * @param Config $eavConfig
     * @param ConfigHelper $configHelper
     * @param AlgoliaHelper $algoliaHelper
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param ManagerInterface $eventManager
     * @param Visibility $visibility
     * @param Stock $stockHelper
     * @param StockRegistryInterface $stockRegistry
     * @param CurrencyHelper $currencyManager
     * @param CategoryHelper $categoryHelper
     * @param PriceManager $priceManager
     * @param Type $productType
     * @param CollectionFactory $productCollectionFactory
     * @param GroupCollection $groupCollection
     * @param ImageHelper $imageHelper
     */
    public function __construct(
        Config                 $eavConfig,
        ConfigHelper           $configHelper,
        AlgoliaHelper          $algoliaHelper,
        Logger                 $logger,
        StoreManagerInterface  $storeManager,
        ManagerInterface       $eventManager,
        Visibility             $visibility,
        Stock                  $stockHelper,
        StockRegistryInterface $stockRegistry,
        CurrencyHelper         $currencyManager,
        CategoryHelper         $categoryHelper,
        PriceManager           $priceManager,
        Type                   $productType,
        CollectionFactory      $productCollectionFactory,
        GroupCollection        $groupCollection,
        ImageHelper            $imageHelper
    )
    {
        $this->eavConfig = $eavConfig;
        $this->configHelper = $configHelper;
        $this->algoliaHelper = $algoliaHelper;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->eventManager = $eventManager;
        $this->visibility = $visibility;
        $this->stockHelper = $stockHelper;
        $this->stockRegistry = $stockRegistry;
        $this->currencyManager = $currencyManager;
        $this->categoryHelper = $categoryHelper;
        $this->priceManager = $priceManager;
        $this->productType = $productType;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->groupCollection = $groupCollection;
        $this->imageHelper = $imageHelper;
    }

    /**
     * @return string
     */
    public function getIndexNameSuffix()
    {
        return '_products';
    }

    /**
     * @param $addEmptyRow
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAllAttributes($addEmptyRow = false)
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
    public function isAttributeEnabled($additionalAttributes, $attributeName)
    {
        foreach ($additionalAttributes as $attr) {
            if ($attr['attribute'] === $attributeName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $storeId
     * @param $productIds
     * @param $onlyVisible
     * @param $includeNotVisibleIndividually
     * @return Collection
     */
    public function getProductCollectionQuery(
        $storeId,
        $productIds = null,
        $onlyVisible = true,
        $includeNotVisibleIndividually = false
    )
    {
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

        /* @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
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

        return $products;
    }

    /**
     * @param $products
     * @param $storeId
     * @return void
     */
    protected function addStockFilter($products, $storeId)
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
     * @param $products
     * @return void
     */
    protected function addMandatoryAttributes(ProductCollection $products): void
    {
        /** @var ProductCollection $products */
        $products->addFinalPrice()
            ->addAttributeToSelect('special_price')
            ->addAttributeToSelect('special_from_date')
            ->addAttributeToSelect('special_to_date')
            ->addAttributeToSelect('visibility')
            ->addAttributeToSelect('status');
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getAdditionalAttributes($storeId = null)
    {
        return $this->configHelper->getProductAdditionalAttributes($storeId);
    }

    /**
     * @param $indexName
     * @param $indexNameTmp
     * @param $storeId
     * @param $saveToTmpIndicesToo
     * @return void
     * @throws AlgoliaException
     */
    public function setSettings($indexName, $indexNameTmp, $storeId, $saveToTmpIndicesToo = false)
    {
        $searchableAttributes = $this->getSearchableAttributes($storeId);
        $customRanking = $this->getCustomRanking($storeId);
        $unretrievableAttributes = $this->getUnretrieveableAttributes($storeId);
        $attributesForFaceting = $this->getAttributesForFaceting($storeId);

        $indexSettings = [
            'searchableAttributes' => $searchableAttributes,
            'customRanking' => $customRanking,
            'unretrievableAttributes' => $unretrievableAttributes,
            'attributesForFaceting'   => $attributesForFaceting,
            'maxValuesPerFacet'       => (int)$this->configHelper->getMaxValuesPerFacet($storeId),
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

        $this->algoliaHelper->setSettings($indexName, $indexSettings, false, true);
        $this->logger->log('Settings: ' . json_encode($indexSettings));
        if ($saveToTmpIndicesToo === true) {
            $this->algoliaHelper->setSettings($indexNameTmp, $indexSettings, false, true, $indexName);
            $this->logger->log('Pushing the same settings to TMP index as well');
        }

        $this->setFacetsQueryRules($indexName);
        if ($saveToTmpIndicesToo === true) {
            $this->setFacetsQueryRules($indexNameTmp);
        }

        /*
         * Handle replicas
         */
        $sortingIndices = $this->configHelper->getSortingIndices($indexName, $storeId);

        $replicas = [];

        if ($this->configHelper->isInstantEnabled($storeId)) {
            $replicas = array_values(array_map(function ($sortingIndex) {
                return $sortingIndex['name'];
            }, $sortingIndices));
        }

        // Managing Virtual Replica
        if ($this->configHelper->useVirtualReplica($storeId)) {
            $replicas = $this->handleVirtualReplica($replicas, $indexName);
        }

        // Merge current replicas with sorting replicas to not delete A/B testing replica indices
        try {
            $currentSettings = $this->algoliaHelper->getSettings($indexName);
            if (is_array($currentSettings) && array_key_exists('replicas', $currentSettings)) {
                $replicas = array_values(array_unique(array_merge($replicas, $currentSettings['replicas'])));
            }
        } catch (AlgoliaException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        if (count($replicas) > 0) {
            $this->algoliaHelper->setSettings($indexName, ['replicas' => $replicas]);
            $this->logger->log('Setting replicas to "' . $indexName . '" index.');
            $this->logger->log('Replicas: ' . json_encode($replicas));
            $setReplicasTaskId = $this->algoliaHelper->getLastTaskId();

            if (!$this->configHelper->useVirtualReplica($storeId)) {
                foreach ($sortingIndices as $values) {
                    $replicaName = $values['name'];
                    $indexSettings['ranking'] = $values['ranking'];
                    $this->algoliaHelper->setSettings($replicaName, $indexSettings, false, true);
                    $this->logger->log('Setting settings to "' . $replicaName . '" replica.');
                    $this->logger->log('Settings: ' . json_encode($indexSettings));
                }
            } else {
                foreach ($sortingIndices as $values) {
                    $replicaName = $values['name'];
                    array_unshift($customRanking, $values['ranking'][0]);
                    $replicaSetting['customRanking'] = $customRanking;
                    $this->algoliaHelper->setSettings($replicaName, $replicaSetting, false, false);
                    $this->logger->log('Setting settings to "' . $replicaName . '" replica.');
                    $this->logger->log('Settings: ' . json_encode($replicaSetting));
                }
            }
        } else {
            $this->algoliaHelper->setSettings($indexName, ['replicas' => []]);
            $this->logger->log('Removing replicas from "' . $indexName . '" index');
            $setReplicasTaskId = $this->algoliaHelper->getLastTaskId();
        }

        // Commented out as it doesn't delete anything now because of merging replica indices earlier
        // $this->deleteUnusedReplicas($indexName, $replicas, $setReplicasTaskId);

        if ($saveToTmpIndicesToo === true) {
            try {
                $this->algoliaHelper->copySynonyms($indexName, $indexNameTmp);
                $this->logger->log('
                    Copying synonyms from production index to TMP one to not erase them with the index move.
                ');
            } catch (AlgoliaException $e) {
                $this->logger->error('Error encountered while copying synonyms: ' . $e->getMessage());
            }

            try {
                $this->algoliaHelper->copyQueryRules($indexName, $indexNameTmp);
                $this->logger->log('
                    Copying query rules to "' . $indexNameTmp . '" to not to erase them with the index move.
                ');
            } catch (AlgoliaException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param $categoryIds
     * @param $storeId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAllCategories($categoryIds, $storeId)
    {
        $filterNotIncludedCategories = !$this->configHelper->showCatsNotIncludedInNavigation($storeId);
        $categories = $this->categoryHelper->getCoreCategories($filterNotIncludedCategories, $storeId);

        $selectedCategories = [];
        foreach ($categoryIds as $id) {
            if (isset($categories[$id])) {
                $selectedCategories[] = $categories[$id];
            }
        }

        return $selectedCategories;
    }

    /**
     * @param Product $product
     * @return array|mixed|null
     * @throws \Exception
     */
    public function getObject(Product $product)
    {
        $storeId = $product->getStoreId();

        $this->logger->start('CREATE RECORD ' . $product->getId() . ' ' . $this->logger->getStoreName($storeId));
        $defaultData = [];

        $transport = new DataObject($defaultData);
        $this->eventManager->dispatch(
            'algolia_product_index_before',
            ['product' => $product, 'custom_data' => $transport]
        );

        $defaultData = $transport->getData();

        $visibility = $product->getVisibility();

        $visibleInCatalog = $this->visibility->getVisibleInCatalogIds();
        $visibleInSearch = $this->visibility->getVisibleInSearchIds();

        $urlParams = [
            '_secure' => $this->configHelper->useSecureUrlsInFrontend($product->getStoreId()),
            '_nosid'  => true,
        ];

        $customData = [
            'objectID'           => $product->getId(),
            'name'               => $product->getName(),
            'url'                => $product->getUrlModel()->getUrl($product, $urlParams),
            'visibility_search'  => (int)(in_array($visibility, $visibleInSearch)),
            'visibility_catalog' => (int)(in_array($visibility, $visibleInCatalog)),
            'type_id'            => $product->getTypeId(),
        ];

        $additionalAttributes = $this->getAdditionalAttributes($product->getStoreId());
        $groups = null;

        $customData = $this->addAttribute('description', $defaultData, $customData, $additionalAttributes, $product);
        $customData = $this->addAttribute('ordered_qty', $defaultData, $customData, $additionalAttributes, $product);
        $customData = $this->addAttribute('total_ordered', $defaultData, $customData, $additionalAttributes, $product);
        $customData = $this->addAttribute('rating_summary', $defaultData, $customData, $additionalAttributes, $product);
        $customData = $this->addCategoryData($customData, $product);
        $customData = $this->addImageData($customData, $product, $additionalAttributes);
        $customData = $this->addInStock($defaultData, $customData, $product);
        $customData = $this->addStockQty($defaultData, $customData, $additionalAttributes, $product);
        if ($product->getTypeId() == "bundle") {
            $customData = $this->addBundleProductDefaultOptions($customData, $product);
        }
        $subProducts = $this->getSubProducts($product);
        $customData = $this->addAdditionalAttributes($customData, $additionalAttributes, $product, $subProducts);
        $customData = $this->priceManager->addPriceDataByProductType($customData, $product, $subProducts);
        $transport = new DataObject($customData);
        $this->eventManager->dispatch(
            'algolia_subproducts_index',
            [
                'custom_data'   => $transport,
                'sub_products'  => $subProducts,
                'productObject' => $product
            ]
        );
        $customData = $transport->getData();
        $customData = array_merge($customData, $defaultData);
        $this->algoliaHelper->castProductObject($customData);
        $transport = new DataObject($customData);
        $this->eventManager->dispatch(
            'algolia_after_create_product_object',
            [
                'custom_data'   => $transport,
                'sub_products'  => $subProducts,
                'productObject' => $product
            ]
        );
        $customData = $transport->getData();

        $this->logger->stop('CREATE RECORD ' . $product->getId() . ' ' . $this->logger->getStoreName($storeId));

        return $customData;
    }

    /**
     * @param Product $product
     * @return array|\Magento\Catalog\Api\Data\ProductInterface[]|DataObject[]
     */
    protected function getSubProducts(Product $product)
    {
        $type = $product->getTypeId();

        if (!in_array($type, ['bundle', 'grouped', 'configurable'], true)) {
            return [];
        }

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
            } catch (ProductReindexingException $e) {
                unset($subProducts[$index]);
            }
        }

        return $subProducts;
    }

    /**
     * Returns all parent product IDs, e.g. when simple product is part of configurable or bundle
     *
     * @param array $productIds
     *
     * @return array
     */
    public function getParentProductIds(array $productIds)
    {
        $parentIds = [];
        foreach ($this->getCompositeTypes() as $typeInstance) {
            $parentIds = array_merge($parentIds, $typeInstance->getParentIdsByChild($productIds));
        }

        return $parentIds;
    }

    /**
     * Returns composite product type instances
     *
     * @return AbstractType[]
     *
     * @see \Magento\Catalog\Model\Indexer\Product\Flat\AbstractAction::_getProductTypeInstances
     */
    protected function getCompositeTypes()
    {
        if ($this->compositeTypes === null) {
            $productEmulator = new \Magento\Framework\DataObject();
            foreach ($this->productType->getCompositeTypes() as $typeId) {
                $productEmulator->setTypeId($typeId);
                $this->compositeTypes[$typeId] = $this->productType->factory($productEmulator);
            }
        }

        return $this->compositeTypes;
    }

    /**
     * @param $attribute
     * @param $defaultData
     * @param $customData
     * @param $additionalAttributes
     * @param Product $product
     * @return mixed
     */
    protected function addAttribute($attribute, $defaultData, $customData, $additionalAttributes, Product $product)
    {
        if (isset($defaultData[$attribute]) === false
            && $this->isAttributeEnabled($additionalAttributes, $attribute)) {
            $customData[$attribute] = $product->getData($attribute);
        }

        return $customData;
    }

    protected function getCategoryPaths($product, $category)
    {
        $category->getUrlInstance()->setStore($product->getStoreId());
        $path = [];

        foreach ($category->getPathIds() as $treeCategoryId) {
            $name = $this->categoryHelper->getCategoryName($treeCategoryId, $storeId);
            if ($name) {
                $categoryIds[] = $treeCategoryId;
                $path[] = $name;
            }
        }
    }

    /**
     * A category should only be indexed if in the path of the current store and has a valid name.
     *
     * @param $category
     * @param $rootCat
     * @param $storeId
     * @return string|null
     */
    protected function getValidCategoryName($category, $rootCat, $storeId): ?string
    {
        $pathParts = explode('/', $category->getPath());
        if (isset($pathParts[1]) && $pathParts[1] !== $rootCat) {
            return null;
        }

        return $this->categoryHelper->getCategoryName($category->getId(), $storeId);

    }

    /**
     * Filter out non unique category path entries.
     *
     * @param $paths
     * @return array
     */
    protected function dedupePaths($paths): array
    {
        return array_intersect_key(
            $paths,
            array_unique(array_map('serialize', $paths))
        );
    }

    /**
     * @param $customData
     * @param Product $product
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function  addBundleProductDefaultOptions($customData, Product $product) {
        $optionsCollection = $product->getTypeInstance(true)->getOptionsCollection($product);
        $optionDetails = [];
        foreach ($optionsCollection as $option){
            $selections = $product->getTypeInstance(true)->getSelectionsCollection($option->getOptionId(),$product);
            //selection details by optionids
            foreach ($selections as $selection) {
                if($selection->getIsDefault()){
                    $optionDetails[$option->getOptionId()] = $selection->getSelectionId();
                }
            }
        }
        $customData['default_bundle_options'] = array_unique($optionDetails);
        return $customData;
    }

    /**
     * For a given product extract category data including category names, parent paths and all category tree IDs
     *
     * @param Product $product
     * @return array|array[]
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function buildCategoryData(Product $product): array
    {
        // Build within a single loop
        // TODO: Profile for efficiency vs separate loops
        $categoryData = [
            'categoryNames'      => [],
            'categoryIds'        => [],
            'categoriesWithPath' => [],
        ];

        $storeId = $product->getStoreId();

        $_categoryIds = $product->getCategoryIds();

        if (is_array($_categoryIds) && count($_categoryIds)) {
            $categoryCollection = $this->getAllCategories($_categoryIds, $storeId);

            /** @var Store $store */
            $store = $this->storeManager->getStore($product->getStoreId());
            $rootCat = $store->getRootCategoryId();

            foreach ($categoryCollection as $category) {
                $categoryName = $this->getValidCategoryName($category, $rootCat, $storeId);
                if (!$categoryName) {
                    continue;
                }
                $categoryData['categoryNames'][] = $categoryName;

                $category->getUrlInstance()->setStore($storeId);
                $paths = [];

                foreach ($category->getPathIds() as $treeCategoryId) {
                    $name = $this->categoryHelper->getCategoryName($treeCategoryId, $storeId);
                    if ($name) {
                        $categoryData['categoryIds'][] = $treeCategoryId;
                        $paths[] = $name;
                    }
                }

                $categoryData['categoriesWithPath'][] = $paths;
            }
        }

        // TODO: Evaluate use cases
        // Based on old extraneous array manip logic (since removed) - is this still a likely scenario?
        $categoryData['categoriesWithPath'] = $this->dedupePaths($categoryData['categoriesWithPath']);

        return $categoryData;
    }

    /**
     * Flatten non hierarchical paths for merchandising
     *
     * @param array $paths
     * @return array
     */
    protected function flattenCategoryPaths(array $paths, int $storeId): array
    {
        return array_map(
            function ($path) use ($storeId) { return implode($this->configHelper->getCategorySeparator($storeId), $path); },
            $paths
        );
    }

    /**
     * Take an array of paths where each element is an array of parent-child hierarchies and
     * append to the top level array each possible parent iteration.
     * This serves to emulate anchoring in Magento in order to use category page id filtering
     * without explicit category assignment.
     *
     * @param array $paths
     * @return array
     */
    protected function autoAnchorParentCategories(array $paths): array {
        foreach ($paths as $path) {
            for ($i = count($path) - 1; $i > 0; $i--) {
                $paths[] = array_slice($path,0, $i);
            }
        }
        return $this->dedupePaths($paths);
    }

    /**
     * @param array $algoliaData Data for product object to be serialized to Algolia index
     * @param Product $product
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function addCategoryData(array $algoliaData, Product $product): array
    {
        $storeId = $product->getStoreId();

        $categoryData = $this->buildCategoryData($product);
        $hierarchicalCategories = $this->getHierarchicalCategories($categoryData['categoriesWithPath'], $storeId);
        $algoliaData['categories'] = $hierarchicalCategories;
        $algoliaData['categories_without_path'] = $categoryData['categoryNames'];
        $algoliaData['categoryIds'] = array_values(array_unique($categoryData['categoryIds']));

        if ($this->configHelper->isVisualMerchEnabled($storeId)) {
            $autoAnchorPaths = $this->autoAnchorParentCategories($categoryData['categoriesWithPath']);
            $algoliaData[$this->configHelper->getCategoryPageIdAttributeName($storeId)] = $this->flattenCategoryPaths($autoAnchorPaths, $storeId);
        }

        return $algoliaData;
    }

    /**
     * @param array $categoriesWithPath
     * @param int $storeId
     * @return array
     */
    protected function getHierarchicalCategories(array $categoriesWithPath, int $storeId): array
    {
        $hierarchicalCategories = [];

        $levelName = 'level';

        foreach ($categoriesWithPath as $category) {
            $categoryCount = count($category);
            for ($i = 0; $i < $categoryCount; $i++) {
                if (isset($hierarchicalCategories[$levelName . $i]) === false) {
                    $hierarchicalCategories[$levelName . $i] = [];
                }

                if ($category[$i] === null) {
                    continue;
                }

                $hierarchicalCategories[$levelName . $i][] = implode($this->configHelper->getCategorySeparator($storeId), array_slice($category, 0, $i + 1));
            }
        }

        // dedupe in case of multi category assignment
        foreach ($hierarchicalCategories as &$level) {
            $level = array_values(array_unique($level));
        }

        return $hierarchicalCategories;
    }

    /**
     * @param array $customData
     * @param Product $product
     * @param $additionalAttributes
     * @return array
     */
    protected function addImageData(array $customData, Product $product, $additionalAttributes)
    {
        if (false === isset($customData['thumbnail_url'])) {
            $customData['thumbnail_url'] = $this->imageHelper
                ->init($product, 'product_thumbnail_image')
                ->getUrl();
        }

        if (false === isset($customData['image_url'])) {
            $this->imageHelper
                ->init($product, $this->configHelper->getImageType())
                ->resize($this->configHelper->getImageWidth(), $this->configHelper->getImageHeight());

            $customData['image_url'] = $this->imageHelper->getUrl();

            if ($this->isAttributeEnabled($additionalAttributes, 'media_gallery')) {
                $product->load($product->getId(), 'media_gallery');

                $customData['media_gallery'] = [];

                $images = $product->getMediaGalleryImages();
                if ($images) {
                    foreach ($images as $image) {
                        $customData['media_gallery'][] = $image->getUrl();
                    }
                }
            }
        }

        return $customData;
    }

    /**
     * @param $defaultData
     * @param $customData
     * @param Product $product
     * @return mixed
     */
    protected function addInStock($defaultData, $customData, Product $product)
    {
        if (isset($defaultData['in_stock']) === false) {
            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            $customData['in_stock'] = $product->isSaleable() && $stockItem->getIsInStock();
        }

        return $customData;
    }

    /**
     * @param $defaultData
     * @param $customData
     * @param $additionalAttributes
     * @param Product $product
     * @return mixed
     */
    protected function addStockQty($defaultData, $customData, $additionalAttributes, Product $product)
    {
        if (isset($defaultData['stock_qty']) === false
            && $this->isAttributeEnabled($additionalAttributes, 'stock_qty')) {
            $customData['stock_qty'] = 0;

            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            if ($stockItem) {
                $customData['stock_qty'] = (int)$stockItem->getQty();
            }
        }

        return $customData;
    }

    /**
     * @param $customData
     * @param $additionalAttributes
     * @param Product $product
     * @param $subProducts
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function addAdditionalAttributes($customData, $additionalAttributes, Product $product, $subProducts)
    {
        foreach ($additionalAttributes as $attribute) {
            $attributeName = $attribute['attribute'];

            if (isset($customData[$attributeName]) && $attributeName !== 'sku') {
                continue;
            }

            /** @var \Magento\Catalog\Model\ResourceModel\Product $resource */
            $resource = $product->getResource();

            /** @var AttributeResource $attributeResource */
            $attributeResource = $resource->getAttribute($attributeName);
            if (!$attributeResource) {
                continue;
            }

            $attributeResource = $attributeResource->setData('store_id', $product->getStoreId());

            $value = $product->getData($attributeName);

            if ($value !== null) {
                $customData = $this->addNonNullValue($customData, $value, $product, $attribute, $attributeResource);

                if (!in_array($attributeName, $this->attributesToIndexAsArray, true)) {
                    continue;
                }
            }

            $type = $product->getTypeId();
            if ($type !== 'configurable' && $type !== 'grouped' && $type !== 'bundle') {
                continue;
            }

            $customData = $this->addNullValue($customData, $subProducts, $attribute, $attributeResource);
        }

        return $customData;
    }

    /**
     * @param $customData
     * @param $subProducts
     * @param $attribute
     * @param AttributeResource $attributeResource
     * @return mixed
     */
    protected function addNullValue($customData, $subProducts, $attribute, AttributeResource $attributeResource)
    {
        $attributeName = $attribute['attribute'];

        $values = [];
        $subProductImages = [];

        if (isset($customData[$attributeName])) {
            $values[] = $customData[$attributeName];
        }

        /** @var Product $subProduct */
        foreach ($subProducts as $subProduct) {
            $value = $subProduct->getData($attributeName);
            if ($value) {
                /** @var string|array $valueText */
                $valueText = $subProduct->getAttributeText($attributeName);

                $values = array_merge($values, $this->getValues($valueText, $subProduct, $attributeResource));
                if ($this->configHelper->useAdaptiveImage($attributeResource->getStoreId())) {
                    $subProductImages = $this->addSubProductImage(
                        $subProductImages,
                        $attribute,
                        $subProduct,
                        $valueText
                    );
                }
            }
        }

        if (is_array($values) && count($values) > 0) {
            $customData[$attributeName] = $this->getSanitizedArrayValues($values, $attributeName);
        }

        if (count($subProductImages) > 0) {
            $customData['images_data'] = $subProductImages;
        }

        return $customData;
    }

    /**
     * By default Algolia will remove all redundant attribute values that are fetched from the child simple products.
     *
     * Overridable via Preference to allow implementer to enforce their own uniqueness rules while leveraging existing indexing code.
     * e.g. $values = (in_array($attributeName, self::NON_UNIQUE_ATTRIBUTES)) ? $values : array_unique($values);
     *
     * @param array $values
     * @param string $attributeName
     * @return array
     */
    protected function getSanitizedArrayValues(array $values, string $attributeName): array
    {
        return array_values(array_unique($values));
    }

    /**
     * @param string|array $valueText - bit of a misnomer - essentially the retrieved values to be indexed for a given product's attribute
     * @param Product $subProduct - the simple product to index
     * @param AttributeResource $attributeResource - the attribute being indexed
     * @return array
     */
    protected function getValues($valueText, Product $subProduct, AttributeResource $attributeResource): array
    {
        $values = [];

        if ($valueText) {
            if (is_array($valueText)) {
                foreach ($valueText as $valueText_elt) {
                    $values[] = $valueText_elt;
                }
            } else {
                $values[] = $valueText;
            }
        } else {
            $values[] = $attributeResource->getFrontend()->getValue($subProduct);
        }

        return $values;
    }

    /**
     * @param $subProductImages
     * @param $attribute
     * @param $subProduct
     * @param $valueText
     * @return mixed
     */
    protected function addSubProductImage($subProductImages, $attribute, $subProduct, $valueText)
    {
        if (mb_strtolower($attribute['attribute'], 'utf-8') !== 'color') {
            return $subProductImages;
        }

        $image = $this->imageHelper
            ->init($subProduct, $this->configHelper->getImageType())
            ->resize(
                $this->configHelper->getImageWidth(),
                $this->configHelper->getImageHeight()
            );

        $subImage = $subProduct->getData($image->getType());
        if (!$subImage || $subImage === 'no_selection') {
            return $subProductImages;
        }

        try {
            $textValueInLower = mb_strtolower($valueText, 'utf-8');
            $subProductImages[$textValueInLower] = $image->getUrl();
        } catch (\Exception $e) {
            $this->logger->log($e->getMessage());
            $this->logger->log($e->getTraceAsString());
        }

        return $subProductImages;
    }

    /**
     * @param $customData
     * @param $value
     * @param Product $product
     * @param $attribute
     * @param AttributeResource $attributeResource
     * @return mixed
     */
    protected function addNonNullValue(
        $customData,
        $value,
        Product $product,
        $attribute,
        AttributeResource $attributeResource
    )
    {
        $valueText = null;

        if (!is_array($value) && $attributeResource->usesSource()) {
            $valueText = $product->getAttributeText($attribute['attribute']);
        }

        if ($valueText) {
            $value = $valueText;
        } else {
            $attributeResource = $attributeResource->setData('store_id', $product->getStoreId());
            $value = $attributeResource->getFrontend()->getValue($product);
        }

        if ($value) {
            $customData[$attribute['attribute']] = $value;
        }

        return $customData;
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
    protected function getCustomRanking($storeId)
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
     */
    protected function getAttributesForFaceting($storeId)
    {
        $attributesForFaceting = [];

        $currencies = $this->currencyManager->getConfigAllowCurrencies();

        $facets = $this->configHelper->getFacets($storeId);
        foreach ($facets as $facet) {
            if ($facet['attribute'] === 'price') {
                foreach ($currencies as $currency_code) {
                    $facet['attribute'] = 'price.' . $currency_code . '.default';

                    if ($this->configHelper->isCustomerGroupsEnabled($storeId)) {
                        foreach ($this->groupCollection as $group) {
                            $group_id = (int)$group->getData('customer_group_id');

                            $attributesForFaceting[] = 'price.' . $currency_code . '.group_' . $group_id;
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
     * @param $indexName
     * @param $replicas
     * @param $setReplicasTaskId
     * @return void
     */
    protected function deleteUnusedReplicas($indexName, $replicas, $setReplicasTaskId)
    {
        $indicesToDelete = [];

        $allIndices = $this->algoliaHelper->listIndexes();
        foreach ($allIndices['items'] as $indexInfo) {
            if (mb_strpos($indexInfo['name'], $indexName) !== 0 || $indexInfo['name'] === $indexName) {
                continue;
            }

            if (mb_strpos($indexInfo['name'], '_tmp') === false && in_array($indexInfo['name'], $replicas) === false) {
                $indicesToDelete[] = $indexInfo['name'];
            }
        }

        if (count($indicesToDelete) > 0) {
            $this->algoliaHelper->waitLastTask($indexName, $setReplicasTaskId);

            foreach ($indicesToDelete as $indexToDelete) {
                $this->algoliaHelper->deleteIndex($indexToDelete);
            }
        }
    }

    /**
     * @param $indexName
     * @return void
     * @throws AlgoliaException
     */
    protected function setFacetsQueryRules($indexName)
    {
        $index = $this->algoliaHelper->getIndex($indexName);

        $this->clearFacetsQueryRules($index);

        $rules = [];
        $facets = $this->configHelper->getFacets();
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
                'objectID' => 'filter_' . $attribute,
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
            $index->saveRules($rules, [
                'forwardToReplicas' => true,
            ]);
        }
    }

    /**
     * @param SearchIndex $index
     * @return void
     * @throws AlgoliaException
     */
    protected function clearFacetsQueryRules(SearchIndex $index)
    {
        try {
            $hitsPerPage = 100;
            $page = 0;
            do {
                $fetchedQueryRules = $index->searchRules('', [
                    'context' => 'magento_filters',
                    'page' => $page,
                    'hitsPerPage' => $hitsPerPage,
                ]);

                if (!$fetchedQueryRules || !array_key_exists('hits', $fetchedQueryRules)) {
                    break;
                }

                foreach ($fetchedQueryRules['hits'] as $hit) {
                    $index->deleteRule($hit['objectID'], [
                        'forwardToReplicas' => true,
                    ]);
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
        if ($product->isDeleted() === true) {
            throw (new ProductDeletedException())
                ->withProduct($product)
                ->withStoreId($storeId);
        }

        if ($product->getStatus() == Status::STATUS_DISABLED) {
            throw (new ProductDisabledException())
                ->withProduct($product)
                ->withStoreId($storeId);
        }

        if ($isChildProduct === false && !in_array($product->getVisibility(), [
                Visibility::VISIBILITY_BOTH,
                Visibility::VISIBILITY_IN_SEARCH,
                Visibility::VISIBILITY_IN_CATALOG,
            ])) {
            throw (new ProductNotVisibleException())
                ->withProduct($product)
                ->withStoreId($storeId);
        }

        $isInStock = true;
        if (!$this->configHelper->getShowOutOfStock($storeId)) {
            $isInStock = $this->productIsInStock($product, $storeId);
        }

        if (!$isInStock) {
            throw (new ProductOutOfStockException())
                ->withProduct($product)
                ->withStoreId($storeId);
        }

        return true;
    }

    /**
     * Returns is product in stock
     *
     * @param Product $product
     * @param int $storeId
     *
     * @return bool
     */
    public function productIsInStock($product, $storeId)
    {
        $stockItem = $this->stockRegistry->getStockItem($product->getId());

        return $product->isSaleable() && $stockItem->getIsInStock();
    }

    /**
     * @param $replica
     * @return array
     */
    protected function handleVirtualReplica($replicas, $indexName)
    {
        $virtualReplicaArray = [];
        foreach ($replicas as $replica) {
            $virtualReplicaArray[] = 'virtual(' . $replica . ')';
        }
        return $virtualReplicaArray;
    }
}
