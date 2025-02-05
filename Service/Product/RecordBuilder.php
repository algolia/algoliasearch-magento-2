<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\RecordBuilder\RecordBuilderInterface;
use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exception\ProductDeletedException;
use Algolia\AlgoliaSearch\Exception\ProductDisabledException;
use Algolia\AlgoliaSearch\Exception\ProductNotVisibleException;
use Algolia\AlgoliaSearch\Exception\ProductOutOfStockException;
use Algolia\AlgoliaSearch\Exception\ProductReindexingException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager;
use Algolia\AlgoliaSearch\Helper\Image as ImageHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Magento\Bundle\Model\Product\Type as BundleProductType;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as AttributeResource;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class RecordBuilder implements RecordBuilderInterface
{
    /**
     * @var string[]
     */
    protected array $attributesToIndexAsArray = [
        'sku',
        'color',
    ];

    public function __construct(
        protected ManagerInterface       $eventManager,
        protected DiagnosticsLogger      $logger,
        protected Visibility             $visibility,
        protected StoreManagerInterface  $storeManager,
        protected ConfigHelper           $configHelper,
        protected CategoryHelper         $categoryHelper,
        protected AlgoliaHelper          $algoliaHelper,
        protected ImageHelper            $imageHelper,
        protected StockRegistryInterface $stockRegistry,
        protected PriceManager           $priceManager,
    ){}

    /**
     * Builds a Product record
     *
     * @param DataObject $entity
     * @return array
     *
     * @throws AlgoliaException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws DiagnosticsException
     */
    public function buildRecord(DataObject $entity): array
    {
        if (!$entity instanceof Product) {
            throw new AlgoliaException('Object must be a Product model');
        }

        $product = $entity;

        $storeId = $product->getStoreId();

        $logEventName = 'CREATE RECORD ' . $product->getId() . ' ' . $this->logger->getStoreName($storeId);
        $this->logger->start($logEventName, true);
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
            AlgoliaConnector::ALGOLIA_API_OBJECT_ID => $product->getId(),
            'name'                                  => $product->getName(),
            'url'                                   => $product->getUrlModel()->getUrl($product, $urlParams),
            'visibility_search'                     => (int) (in_array($visibility, $visibleInSearch)),
            'visibility_catalog'                    => (int) (in_array($visibility, $visibleInCatalog)),
            'type_id'                               => $product->getTypeId(),
        ];

        $additionalAttributes = $this->getAdditionalAttributes($product->getStoreId());

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

        $this->logger->stop($logEventName, true);

        return $customData;
    }

    /**
     * @param int|null $storeId
     * @return array
     */
    public function getAdditionalAttributes(?int $storeId = null): array
    {
        return $this->configHelper->getProductAdditionalAttributes($storeId);
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

    /**
     * @param $additionalAttributes
     * @param $attributeName
     * @return bool
     */
    public function isAttributeEnabled($additionalAttributes, $attributeName): bool
    {
        foreach ($additionalAttributes as $attr) {
            if ($attr['attribute'] === $attributeName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $algoliaData Data for product object to be serialized to Algolia index
     * @param Product $product
     * @return mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function addCategoryData(array $algoliaData, Product $product): array
    {
        $this->logger->startProfiling(__METHOD__);
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

        $this->logger->stopProfiling(__METHOD__);
        return $algoliaData;
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
    public function addInStock($defaultData, $customData, Product $product)
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
     * @throws LocalizedException
     */
    protected function addAdditionalAttributes($customData, $additionalAttributes, Product $product, $subProducts)
    {
        $this->logger->startProfiling(__METHOD__);
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
        $this->logger->stopProfiling(__METHOD__);

        return $customData;
    }

    /**
     * For a given product extract category data including category names, parent paths and all category tree IDs
     *
     * @param Product $product
     * @return array|array[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
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
     * @param $categoryIds
     * @param $storeId
     * @return array
     * @throws LocalizedException
     */
    public function getAllCategories($categoryIds, $storeId): array
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
        return array_values(
            array_intersect_key(
                $paths,
                array_unique(array_map('serialize', $paths))
            )
        );
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

        // dedupe in case of multicategory assignment
        foreach ($hierarchicalCategories as &$level) {
            $level = array_values(array_unique($level));
        }

        return $hierarchicalCategories;
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
     * Flatten non-hierarchical paths for merchandising
     *
     * @param array $paths
     * @param int $storeId
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
     * @param $customData
     * @param Product $product
     * @return mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function addBundleProductDefaultOptions($customData, Product $product) {
        $optionsCollection = $product->getTypeInstance()->getOptionsCollection($product);
        $optionDetails = [];
        foreach ($optionsCollection as $option){
            $selections = $product->getTypeInstance()->getSelectionsCollection($option->getOptionId(),$product);
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
     * @param Product $product
     * @return array|ProductInterface[]|DataObject[]
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

        if ($value !== null) {
            $customData[$attribute['attribute']] = $value;
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
     * Returns is product in stock
     *
     * @param Product $product
     * @param int $storeId
     *
     * @return bool
     */
    public function productIsInStock($product, $storeId): bool
    {
        $stockItem = $this->stockRegistry->getStockItem($product->getId());

        return $product->isSaleable() && $stockItem->getIsInStock();
    }
}
