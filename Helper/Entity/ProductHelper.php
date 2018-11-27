<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager;
use Algolia\AlgoliaSearch\Helper\Logger;
use AlgoliaSearch\AlgoliaException;
use AlgoliaSearch\Index;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as AttributeResource;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Helper\Stock;
use Magento\Directory\Model\Currency as CurrencyHelper;
use Magento\Eav\Model\Config;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class ProductHelper
{
    private $eavConfig;
    private $configHelper;
    private $algoliaHelper;
    private $logger;
    private $storeManager;
    private $eventManager;
    private $visibility;
    private $stockHelper;
    private $stockRegistry;
    private $objectManager;
    private $currencyManager;
    private $categoryHelper;
    private $priceManager;
    private $imageHelper;

    /**
     * @var Type
     */
    private $productType;

    /**
     * @var AbstractType[]
     */
    private $compositeTypes;

    private $productAttributes;

    private $predefinedProductAttributes = [
        'name',
        'url_key',
        'image',
        'small_image',
        'thumbnail',
        'msrp_enabled', // Needed to handle MSRP behavior
    ];

    private $createdAttributes = [
        'path',
        'categories',
        'categories_without_path',
        'ordered_qty',
        'total_ordered',
        'stock_qty',
        'rating_summary',
        'media_gallery',
        'in_stock',
    ];

    private $attributesToIndexAsArray = [
        'sku',
        'color',
    ];

    public function __construct(
        Config $eavConfig,
        ConfigHelper $configHelper,
        AlgoliaHelper $algoliaHelper,
        Logger $logger,
        StoreManagerInterface $storeManager,
        ManagerInterface $eventManager,
        Visibility $visibility,
        Stock $stockHelper,
        StockRegistryInterface $stockRegistry,
        ObjectManagerInterface $objectManager,
        CurrencyHelper $currencyManager,
        CategoryHelper $categoryHelper,
        PriceManager $priceManager,
        Type $productType
    ) {
        $this->eavConfig = $eavConfig;
        $this->configHelper = $configHelper;
        $this->algoliaHelper = $algoliaHelper;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->eventManager = $eventManager;
        $this->visibility = $visibility;
        $this->stockHelper = $stockHelper;
        $this->stockRegistry = $stockRegistry;
        $this->objectManager = $objectManager;
        $this->currencyManager = $currencyManager;
        $this->categoryHelper = $categoryHelper;
        $this->priceManager = $priceManager;
        $this->productType = $productType;

        $this->imageHelper = $this->objectManager->create(
            'Algolia\AlgoliaSearch\Helper\Image',
            [
                'options' =>[
                    'shouldRemovePubDir' => $this->configHelper->shouldRemovePubDirectory(),
                ],
            ]
        );
    }

    public function getIndexNameSuffix()
    {
        return '_products';
    }

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
                'landing_page', 'level', 'lower_cms_block', 'page_layout', 'path_in_store', 'position', 'small_image',
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

    public function isAttributeEnabled($additionalAttributes, $attributeName)
    {
        foreach ($additionalAttributes as $attr) {
            if ($attr['attribute'] === $attributeName) {
                return true;
            }
        }

        return false;
    }

    public function getProductCollectionQuery(
        $storeId,
        $productIds = null,
        $onlyVisible = true,
        $includeNotVisibleIndividually = false
    ) {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $products */
        $products = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Product\Collection');

        $products = $products
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->distinct(true);

        if ($onlyVisible) {
            $products = $products->addAttributeToFilter('status', ['=' => Status::STATUS_ENABLED]);

            if ($includeNotVisibleIndividually === false) {
                $products = $products
                    ->addAttributeToFilter('visibility', ['in' => $this->visibility->getVisibleInSiteIds()]);
            }

            if ($this->configHelper->getShowOutOfStock($storeId) === false) {
                $this->stockHelper->addInStockFilterToCollection($products);
            }
        }

        /* @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        $products = $products->addFinalPrice()
            ->addAttributeToSelect('special_price')
            ->addAttributeToSelect('special_from_date')
            ->addAttributeToSelect('special_to_date')
            ->addAttributeToSelect('visibility')
            ->addAttributeToSelect('status');

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

    public function getAdditionalAttributes($storeId = null)
    {
        return $this->configHelper->getProductAdditionalAttributes($storeId);
    }

    public function setSettings($indexName, $indexNameTmp, $storeId, $saveToTmpIndicesToo = false)
    {
        $searchableAttributes = $this->getSearchableAttributes();
        $customRanking = $this->getCustomRanking($storeId);
        $unretrievableAttributes = $this->getUnretrieveableAttributes();
        $attributesForFaceting = $this->getAttributesForFaceting($storeId);

        $indexSettings = [
            'searchableAttributes'    => $searchableAttributes,
            'customRanking'           => $customRanking,
            'unretrievableAttributes' => $unretrievableAttributes,
            'attributesForFaceting'   => $attributesForFaceting,
            'maxValuesPerFacet'       => (int) $this->configHelper->getMaxValuesPerFacet($storeId),
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
                'store_id'       => $storeId,
                'index_settings' => $transport,
            ]
        );
        $indexSettings = $transport->getData();

        $this->algoliaHelper->setSettings($indexName, $indexSettings, false, true);
        if ($saveToTmpIndicesToo === true) {
            $this->algoliaHelper->setSettings($indexNameTmp, $indexSettings, false, true);
        }

        $this->setFacetsQueryRules($indexName);
        if ($saveToTmpIndicesToo === true) {
            $this->setFacetsQueryRules($indexNameTmp);
        }

        /*
         * Handle replicas
         */
        $sortingIndices = $this->configHelper->getSortingIndices($indexName, $storeId);

        $replicas = array_values(array_map(function ($sortingIndex) {
            return $sortingIndex['name'];
        }, $sortingIndices));

        // Merge current replicas with sorting replicas to not delete A/B testing replica indices
        try {
            $currentSettings = $this->algoliaHelper->getSettings($indexName);
            if (array_key_exists('replicas', $currentSettings)) {
                $replicas = array_values(array_unique(array_merge($replicas, $currentSettings['replicas'])));
            }
        } catch (AlgoliaException $e) {
            if ($e->getMessage() !== 'Index does not exist') {
                throw $e;
            }
        }

        if (count($replicas) > 0) {
            $this->algoliaHelper->setSettings($indexName, ['replicas' => $replicas]);
            $setReplicasTaskId = $this->algoliaHelper->getLastTaskId();

            foreach ($sortingIndices as $values) {
                $indexSettings['ranking'] = $values['ranking'];
                $this->algoliaHelper->setSettings($values['name'], $indexSettings, false, true);
            }
        } else {
            $this->algoliaHelper->setSettings($indexName, ['replicas' => []]);
            $setReplicasTaskId = $this->algoliaHelper->getLastTaskId();
        }

        // Commented out as it doesn't delete anything now because of merging replica indices earlier
        // $this->deleteUnusedReplicas($indexName, $replicas, $setReplicasTaskId);

        if ($this->configHelper->isEnabledSynonyms($storeId) === true) {
            if ($synonymsFile = $this->configHelper->getSynonymsFile($storeId)) {
                $synonymsToSet = json_decode(file_get_contents($synonymsFile));
            } else {
                $synonymsToSet = [];

                $synonyms = $this->configHelper->getSynonyms($storeId);
                foreach ($synonyms as $objectID => $synonym) {
                    $synonymsToSet[] = [
                        'objectID' => $objectID,
                        'type' => 'synonym',
                        'synonyms' => $this->explodeSynonyms($synonym['synonyms']),
                    ];
                }

                $onewaySynonyms = $this->configHelper->getOnewaySynonyms($storeId);
                foreach ($onewaySynonyms as $objectID => $onewaySynonym) {
                    $synonymsToSet[] = [
                        'objectID' => $objectID,
                        'type' => 'oneWaySynonym',
                        'input' => $onewaySynonym['input'],
                        'synonyms' => $this->explodeSynonyms($onewaySynonym['synonyms']),
                    ];
                }
            }

            $this->algoliaHelper->setSynonyms($saveToTmpIndicesToo ? $indexNameTmp : $indexName, $synonymsToSet);
        } elseif ($saveToTmpIndicesToo === true) {
            $this->algoliaHelper->copySynonyms($indexName, $indexNameTmp);
        }

        if ($saveToTmpIndicesToo === true) {
            $this->algoliaHelper->copyQueryRules($indexName, $indexNameTmp);
        }
    }

    public function getAllCategories($categoryIds)
    {
        $categories = $this->categoryHelper->getCoreCategories();

        $selectedCategories = [];
        foreach ($categoryIds as $id) {
            if (isset($categories[$id])) {
                $selectedCategories[] = $categories[$id];
            }
        }

        return $selectedCategories;
    }

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
            '_nosid' => true,
        ];

        $customData = [
            'objectID'           => $product->getId(),
            'name'               => $product->getName(),
            'url'                => $product->getUrlModel()->getUrl($product, $urlParams),
            'visibility_search'  => (int) (in_array($visibility, $visibleInSearch)),
            'visibility_catalog' => (int) (in_array($visibility, $visibleInCatalog)),
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

        $subProducts = $this->getSubProducts($product);

        $customData = $this->addAdditionalAttributes($customData, $additionalAttributes, $product, $subProducts);

        // old beheviour
//        $customData = $this->priceManager->addPriceData($customData, $product, $subProducts);

        // new behaviour
        $customData = $this->priceManager->addPriceDataByProductType($customData, $product, $subProducts);

        $transport = new DataObject($customData);
        $this->eventManager->dispatch(
            'algolia_subproducts_index',
            ['custom_data' => $transport, 'sub_products' => $subProducts, 'productObject' => $product]
        );
        $customData = $transport->getData();

        $customData = array_merge($customData, $defaultData);

        $this->algoliaHelper->castProductObject($customData);

        $transport = new DataObject($customData);
        $this->eventManager->dispatch(
            'algolia_after_create_product_object',
            ['custom_data' => $transport, 'sub_products' => $subProducts, 'productObject' => $product]
        );
        $customData = $transport->getData();

        $this->logger->stop('CREATE RECORD ' . $product->getId() . ' ' . $this->logger->getStoreName($storeId));

        return $customData;
    }

    private function getSubProducts(Product $product)
    {
        $subProducts = [];
        $ids = null;

        $type = $product->getTypeId();
        if ($type === 'configurable' || $type === 'grouped' || $type === 'bundle') {
            if ($type === 'bundle') {
                $ids = [];

                /** @var \Magento\Bundle\Model\Product\Type $typeInstance */
                $typeInstance = $product->getTypeInstance();

                $selection = $typeInstance->getSelectionsCollection($typeInstance->getOptionsIds($product), $product);
                foreach ($selection as $option) {
                    $ids[] = $option->getProductId();
                }
            }

            if ($type === 'configurable' || $type === 'grouped') {
                $ids = $product->getTypeInstance()->getChildrenIds($product->getId());
                $ids = call_user_func_array('array_merge', $ids);
            }

            if (count($ids)) {
                $storeId = $product->getStoreId();

                $onlyVisible = false;
                if ($this->configHelper->indexOutOfStockOptions($storeId) === false) {
                    $onlyVisible = true;
                }

                $subProducts = $this->getProductCollectionQuery($storeId, $ids, $onlyVisible, true)->load();
            }
        }

        return $subProducts;
    }

    /**
     * Returns all parent product IDs, e.g. when simple product is part of configurable or bundle
     *
     * @param array $productIds
     *
     * @throws \Magento\Framework\Exception\LocalizedException
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
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return AbstractType[]
     *
     * @see \Magento\Catalog\Model\Indexer\Product\Flat\AbstractAction::_getProductTypeInstances
     */
    private function getCompositeTypes()
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

    private function addAttribute($attribute, $defaultData, $customData, $additionalAttributes, Product $product)
    {
        if (isset($defaultData[$attribute]) === false
            && $this->isAttributeEnabled($additionalAttributes, $attribute)) {
            $customData[$attribute] = $product->getData($attribute);
        }

        return $customData;
    }

    private function addCategoryData($customData, Product $product)
    {
        $storeId = $product->getStoreId();

        $categories = [];
        $categoriesWithPath = [];
        $categoryIds = [];

        $_categoryIds = $product->getCategoryIds();

        if (is_array($_categoryIds) && count($_categoryIds) > 0) {
            $categoryCollection = $this->getAllCategories($_categoryIds);

            /** @var Store $store */
            $store = $this->storeManager->getStore($product->getStoreId());
            $rootCat = $store->getRootCategoryId();

            foreach ($categoryCollection as $category) {
                // Check and skip all categories that is not
                // in the path of the current store.
                $path = $category->getPath();
                $pathParts = explode('/', $path);
                if (isset($pathParts[1]) && $pathParts[1] !== $rootCat) {
                    continue;
                }

                $categoryName = $this->categoryHelper->getCategoryName($category->getId(), $storeId);

                if ($categoryName) {
                    $categories[] = $categoryName;
                }

                $category->getUrlInstance()->setStore($product->getStoreId());
                $path = [];

                foreach ($category->getPathIds() as $treeCategoryId) {
                    if (!$this->configHelper->showCatsNotIncludedInNavigation($storeId)
                        && !$this->categoryHelper->isCategoryVisibleInMenu($treeCategoryId, $storeId)) {
                        // If the category should not be included in menu - skip it
                        $path[] = null;
                        continue;
                    }

                    $name = $this->categoryHelper->getCategoryName($treeCategoryId, $storeId);
                    if ($name) {
                        $categoryIds[] = $treeCategoryId;
                        $path[] = $name;
                    }
                }

                $categoriesWithPath[] = $path;
            }
        }

        foreach ($categoriesWithPath as $result) {
            for ($i = count($result) - 1; $i > 0; $i--) {
                $categoriesWithPath[] = array_slice($result, 0, $i);
            }
        }

        $categoriesWithPath = array_intersect_key(
            $categoriesWithPath,
            array_unique(array_map('serialize', $categoriesWithPath))
        );

        $hierarchicalCategories = $this->getHierarchicalCategories($categoriesWithPath);

        $customData['categories'] = $hierarchicalCategories;
        $customData['categories_without_path'] = $categories;
        $customData['categoryIds'] = array_values(array_unique($categoryIds));

        return $customData;
    }

    private function getHierarchicalCategories($categoriesWithPath)
    {
        $hierachivalCategories = [];

        $levelName = 'level';

        foreach ($categoriesWithPath as $category) {
            $categoryCount = count($category);
            for ($i = 0; $i < $categoryCount; $i++) {
                if (isset($hierachivalCategories[$levelName . $i]) === false) {
                    $hierachivalCategories[$levelName . $i] = [];
                }

                if ($category[$i] === null) {
                    continue;
                }

                $hierachivalCategories[$levelName . $i][] = implode(' /// ', array_slice($category, 0, $i + 1));
            }
        }

        foreach ($hierachivalCategories as &$level) {
            $level = array_values(array_unique($level));
        }

        return $hierachivalCategories;
    }

    private function addImageData(array $customData, Product $product, $additionalAttributes)
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
                        $url = $image->getUrl();
                        $url = $this->imageHelper->removeProtocol($url);
                        $url = $this->imageHelper->removeDoubleSlashes($url);

                        $customData['media_gallery'][] = $url;
                    }
                }
            }
        }

        return $customData;
    }

    private function addInStock($defaultData, $customData, Product $product)
    {
        if (isset($defaultData['in_stock']) === false) {
            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            $customData['in_stock'] = $stockItem && (int) $stockItem->getIsInStock();
        }

        return $customData;
    }

    private function addStockQty($defaultData, $customData, $additionalAttributes, Product $product)
    {
        if (isset($defaultData['stock_qty']) === false
            && $this->isAttributeEnabled($additionalAttributes, 'stock_qty')) {
            $customData['stock_qty'] = 0;

            $stockItem = $this->stockRegistry->getStockItem($product->getId());
            if ($stockItem) {
                $customData['stock_qty'] = (int) $stockItem->getQty();
            }
        }

        return $customData;
    }

    private function addAdditionalAttributes($customData, $additionalAttributes, Product $product, $subProducts)
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

    private function addNullValue($customData, $subProducts, $attribute, AttributeResource $attributeResource)
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
                $subProductImages = $this->addSubProductImage($subProductImages, $attribute, $subProduct, $valueText);
            }
        }

        if (is_array($values) && count($values) > 0) {
            $customData[$attributeName] = array_values(array_unique($values));
        }

        if (count($subProductImages) > 0) {
            $customData['images_data'] = $subProductImages;
        }

        return $customData;
    }

    private function getValues($valueText, Product $subProduct, AttributeResource $attributeResource)
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

    private function addSubProductImage($subProductImages, $attribute, $subProduct, $valueText)
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

        try {
            $textValueInLower = mb_strtolower($valueText, 'utf-8');
            $subProductImages[$textValueInLower] = $image->getUrl();
        } catch (\Exception $e) {
            $this->logger->log($e->getMessage());
            $this->logger->log($e->getTraceAsString());
        }

        return $subProductImages;
    }

    private function addNonNullValue(
        $customData,
        $value,
        Product $product,
        $attribute,
        AttributeResource $attributeResource
    ) {
        $valueText = null;

        if (!is_array($value)) {
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

    private function getSearchableAttributes()
    {
        $searchableAttributes = [];

        foreach ($this->getAdditionalAttributes() as $attribute) {
            if ($attribute['searchable'] === '1') {
                if (!isset($attribute['order']) || $attribute['order'] === 'ordered') {
                    $searchableAttributes[] = $attribute['attribute'];
                } else {
                    $searchableAttributes[] = 'unordered(' . $attribute['attribute'] . ')';
                }
            }

            if ($attribute['attribute'] === 'categories') {
                $searchableAttributes[] = (isset($attribute['order']) && $attribute['order'] === 'ordered') ?
                    'categories_without_path' : 'unordered(categories_without_path)';
            }
        }

        $searchableAttributes = array_values(array_unique($searchableAttributes));

        return $searchableAttributes;
    }

    private function getCustomRanking($storeId)
    {
        $customRanking = [];

        $customRankings = $this->configHelper->getProductCustomRanking($storeId);
        foreach ($customRankings as $ranking) {
            $customRanking[] = $ranking['order'] . '(' . $ranking['attribute'] . ')';
        }

        return $customRanking;
    }

    private function getUnretrieveableAttributes()
    {
        $unretrievableAttributes = [];

        foreach ($this->getAdditionalAttributes() as $attribute) {
            if ($attribute['retrievable'] !== '1') {
                $unretrievableAttributes[] = $attribute['attribute'];
            }
        }

        return $unretrievableAttributes;
    }

    private function getAttributesForFaceting($storeId)
    {
        $attributesForFaceting = [];

        $currencies = $this->currencyManager->getConfigAllowCurrencies();

        $facets = $this->configHelper->getFacets();
        foreach ($facets as $facet) {
            if ($facet['attribute'] === 'price') {
                foreach ($currencies as $currency_code) {
                    $facet['attribute'] = 'price.' . $currency_code . '.default';

                    if ($this->configHelper->isCustomerGroupsEnabled($storeId)) {
                        $groupCollection = $this->objectManager
                            ->create('Magento\Customer\Model\ResourceModel\Group\Collection');

                        foreach ($groupCollection as $group) {
                            $group_id = (int) $group->getData('customer_group_id');

                            $attributesForFaceting[] = 'price.' . $currency_code . '.group_' . $group_id;
                        }
                    }

                    $attributesForFaceting[] = $facet['attribute'];
                }
            } else {
                $attribute = $facet['attribute'];
                if (array_key_exists('searchable', $facet) && $facet['searchable'] === '1') {
                    $attribute = 'searchable(' . $attribute . ')';
                }

                $attributesForFaceting[] = $attribute;
            }
        }

        if ($this->configHelper->replaceCategories($storeId) && !in_array('categories', $attributesForFaceting, true)) {
            $attributesForFaceting[] = 'categories';
        }

        // Used for merchandising
        $attributesForFaceting[] = 'categoryIds';

        return $attributesForFaceting;
    }

    private function deleteUnusedReplicas($indexName, $replicas, $setReplicasTaskId)
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

    private function setFacetsQueryRules($indexName)
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

            $rules[] = [
                'objectID' => 'filter_' . $attribute,
                'description' => 'Filter facet "' . $attribute . '"',
                'condition' => [
                    'anchoring' => 'contains',
                    'pattern' => '{facet:' . $attribute . '}',
                    'context' => 'magento_filters',
                ],
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
            $index->batchRules($rules, true);
        }
    }

    private function clearFacetsQueryRules(Index $index)
    {
        $hitsPerPage = 100;
        $page = 0;
        do {
            $fetchedQueryRules = $index->searchRules([
                'context' => 'magento_filters',
                'page' => $page,
                'hitsPerPage' => $hitsPerPage,
            ]);

            if (!$fetchedQueryRules || !array_key_exists('hits', $fetchedQueryRules)) {
                break;
            }

            foreach ($fetchedQueryRules['hits'] as $hit) {
                $index->deleteRule($hit['objectID'], true);
            }

            $page++;
        } while (($page * $hitsPerPage) < $fetchedQueryRules['nbHits']);
    }

    private function explodeSynonyms($synonyms)
    {
        return array_map('trim', explode(',', $synonyms));
    }
}
