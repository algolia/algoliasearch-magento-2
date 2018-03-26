<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Model\Category as MagentoCategory;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Algolia\AlgoliaSearch\Helper\Image;
use Magento\Catalog\Model\Category;
use Magento\Framework\DataObject;

class CategoryHelper
{
    private $eventManager;

    private $objectManager;

    private $storeManager;

    private $resourceConnection;

    private $eavConfig;

    private $configHelper;

    private $isCategoryVisibleInMenuCache;
    private $coreCategories;
    private $idColumn;
    private $categoryAttributes;
    private $rootCategoryId = -1;
    private $activeCategories;
    private $categoryNames;

    public function __construct(
        ManagerInterface $eventManager,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        Config $eavConfig,
        ConfigHelper $configHelper
    ) {
        $this->eventManager = $eventManager;
        $this->objectManager = $objectManager;
        $this->storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;
        $this->eavConfig = $eavConfig;
        $this->configHelper = $configHelper;
    }

    public function getIndexNameSuffix()
    {
        return '_categories';
    }

    public function getIndexSettings($storeId)
    {
        $searchableAttributes = [];
        $unretrievableAttributes = [];

        foreach ($this->configHelper->getCategoryAdditionalAttributes($storeId) as $attribute) {
            if ($attribute['searchable'] === '1') {
                if ($attribute['order'] === 'ordered') {
                    $searchableAttributes[] = $attribute['attribute'];
                } else {
                    $searchableAttributes[] = 'unordered(' . $attribute['attribute'] . ')';
                }
            }

            if ($attribute['retrievable'] !== '1') {
                $unretrievableAttributes[] = $attribute['attribute'];
            }
        }

        $customRankings = $this->configHelper->getCategoryCustomRanking($storeId);

        $customRankingsArr = [];

        foreach ($customRankings as $ranking) {
            $customRankingsArr[] = $ranking['order'] . '(' . $ranking['attribute'] . ')';
        }

        // Default index settings
        $indexSettings = [
            'searchableAttributes'    => array_values(array_unique($searchableAttributes)),
            'customRanking'           => $customRankingsArr,
            'unretrievableAttributes' => $unretrievableAttributes,
        ];

        // Additional index settings from event observer
        $transport = new DataObject($indexSettings);
        $this->eventManager->dispatch('algolia_index_settings_prepare', [ // Only for backward compatibility
                'store_id'       => $storeId,
                'index_settings' => $transport,
            ]);
        $this->eventManager->dispatch('algolia_categories_index_before_set_settings', [
                'store_id'       => $storeId,
                'index_settings' => $transport,
            ]);
        $indexSettings = $transport->getData();

        return $indexSettings;
    }

    public function getAdditionalAttributes($storeId = null)
    {
        return $this->configHelper->getCategoryAdditionalAttributes($storeId);
    }

    public function getCategoryCollectionQuery($storeId, $categoryIds = null)
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore($storeId);
        $storeRootCategoryPath = sprintf('%d/%d', $this->getRootCategoryId(), $store->getRootCategoryId());

        /* @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $categories = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Category\Collection');

        $unserializedCategorysAttrs = $this->getAdditionalAttributes($storeId);

        $additionalAttr = [];

        foreach ($unserializedCategorysAttrs as $attr) {
            $additionalAttr[] = $attr['attribute'];
        }

        $categories
            ->distinct(true)
            ->addPathFilter($storeRootCategoryPath)
            ->addNameToResult()
            ->addUrlRewriteToResult()
            ->addIsActiveFilter()
            ->setStoreId($storeId)
            ->addAttributeToSelect(array_merge(['name'], $additionalAttr))
            ->addFieldToFilter('level', ['gt' => 1]);

        if (!$this->configHelper->showCatsNotIncludedInNavigation()) {
            $categories->addAttributeToFilter('include_in_menu', 1);
        }

        if ($categoryIds) {
            $categories->addFieldToFilter('entity_id', ['in' => $categoryIds]);
        }

        $this->eventManager->dispatch(
            'algolia_after_categories_collection_build',
            ['store' => $storeId, 'collection' => $categories]
        );

        return $categories;
    }

    public function getAllAttributes()
    {
        if (isset($this->categoryAttributes)) {
            return $this->categoryAttributes;
        }

        $this->categoryAttributes = [];

        $allAttributes = $this->eavConfig->getEntityAttributeCodes('catalog_category');

        $categoryAttributes = array_merge($allAttributes, ['product_count']);

        $excludedAttributes = [
            'all_children', 'available_sort_by', 'children', 'children_count', 'custom_apply_to_products',
            'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update',
            'custom_use_parent_settings', 'default_sort_by', 'display_mode', 'filter_price_range',
            'global_position', 'image', 'include_in_menu', 'is_active', 'is_always_include_in_menu', 'is_anchor',
            'landing_page', 'level', 'lower_cms_block', 'page_layout', 'path_in_store', 'position', 'small_image',
            'thumbnail', 'url_key', 'url_path','visible_in_menu',
        ];

        $categoryAttributes = array_diff($categoryAttributes, $excludedAttributes);

        foreach ($categoryAttributes as $attributeCode) {
            /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
            $attribute = $this->eavConfig->getAttribute('catalog_category', $attributeCode);
            $this->categoryAttributes[$attributeCode] = $attribute->getData('frontend_label');
        }

        return $this->categoryAttributes;
    }

    public function getObject(Category $category)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection */
        $productCollection = $category->getProductCollection();
        $productCollection = $productCollection->addMinimalPrice();

        $category->setProductCount($productCollection->getSize());

        $transport = new DataObject();
        $this->eventManager->dispatch(
            'algolia_category_index_before',
            ['category' => $category, 'custom_data' => $transport]
        );
        $customData = $transport->getData();

        $storeId = $category->getStoreId();

        /** @var \Magento\Framework\Url $urlInstance */
        $urlInstance = $category->getUrlInstance();
        $urlInstance->setData('store', $storeId);

        $path = '';
        foreach ($category->getPathIds() as $categoryId) {
            if ($path !== '') {
                $path .= ' / ';
            }
            $path .= $this->getCategoryName($categoryId, $storeId);
        }

        $imageUrl = null;
        try {
            $imageUrl = $category->getImageUrl();
        } catch (\Exception $e) { /* no image, no default: not fatal */
        }

        $data = [
            'objectID'      => $category->getId(),
            'name'          => $category->getName(),
            'path'          => $path,
            'level'         => $category->getLevel(),
            'url'           => $this->getUrl($category),
            'include_in_menu' => $category->getIncludeInMenu(),
            '_tags'         => ['category'],
            'popularity'    => 1,
            'product_count' => $category->getProductCount(),
        ];

        if ($imageUrl) {
            /** @var Image $imageHelper */
            $imageHelper = $this->objectManager->create('Algolia\AlgoliaSearch\Helper\Image');

            $imageUrl = $imageHelper->removeProtocol($imageUrl);
            $imageUrl = $imageHelper->removeDoubleSlashes($imageUrl);

            $data['image_url'] = $imageUrl;
        }

        foreach ($this->configHelper->getCategoryAdditionalAttributes($storeId) as $attribute) {
            $value = $category->getData($attribute['attribute']);

            /** @var \Magento\Catalog\Model\ResourceModel\Category $resource */
            $resource = $category->getResource();

            $attributeResource = $resource->getAttribute($attribute['attribute']);
            if ($attributeResource) {
                $value = $attributeResource->getFrontend()->getValue($category);
            }

            if (isset($data[$attribute['attribute']])) {
                $value = $data[$attribute['attribute']];
            }

            if ($value) {
                $data[$attribute['attribute']] = $value;
            }
        }

        $data = array_merge($data, $customData);

        $transport = new DataObject($data);
        $this->eventManager->dispatch(
            'algolia_after_create_category_object',
            ['category' => $category, 'categoryObject' => $transport]
        );
        $data = $transport->getData();

        return $data;
    }

    public function getRootCategoryId()
    {
        if ($this->rootCategoryId !== -1) {
            return $this->rootCategoryId;
        }

        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Category\Collection');
        $collection->addFieldToFilter('parent_id', 0);
        $collection->getSelect()->limit(1);

        /** @var \Magento\Catalog\Model\Category $rootCategory */
        $rootCategory = $collection->getFirstItem();

        $this->rootCategoryId = $rootCategory->getId();

        return $this->rootCategoryId;
    }

    private function getUrl(Category $category)
    {
        $categoryUrl = $category->getUrl();

        if ($this->configHelper->useSecureUrlsInFrontend($category->getStoreId()) === false) {
            return $categoryUrl;
        }

        $unsecureBaseUrl = $category->getUrlInstance()->getBaseUrl(['_secure' => false]);
        $secureBaseUrl = $category->getUrlInstance()->getBaseUrl(['_secure' => true]);

        if (strpos($categoryUrl, $unsecureBaseUrl) === 0) {
            return substr_replace($categoryUrl, $secureBaseUrl, 0, mb_strlen($unsecureBaseUrl));
        }

        return $categoryUrl;
    }

    public function isCategoryActive($categoryId, $storeId = null)
    {
        $storeId = (int) $storeId;
        $categoryId = (int) $categoryId;

        if ($path = $this->getCategoryPath($categoryId, $storeId)) {
            // Check whether the specified category is active

            $isActive = true; // Check whether all parent categories for the current category are active
            $parentCategoryIds = explode('/', $path);

            if (count($parentCategoryIds) <= 2) { // Exclude root category
                return false;
            }

            array_shift($parentCategoryIds); // Remove root category

            array_pop($parentCategoryIds); // Remove current category as it is already verified

            $parentCategoryIds = array_reverse($parentCategoryIds); // Start from the first parent

            foreach ($parentCategoryIds as $parentCategoryId) {
                if (!($parentCategoryPath = $this->getCategoryPath($parentCategoryId, $storeId))) {
                    $isActive = false;
                    break;
                }
            }

            if ($isActive) {
                return true;
            }
        }

        return false;
    }

    private function getCategoryPath($categoryId, $storeId = null)
    {
        $categories = $this->getCategories();
        $storeId = (int) $storeId;
        $categoryId = (int) $categoryId;
        $path = null;

        $categoryKeyId = $categoryId;

        if ($this->getCorrectIdColumn() === 'row_id') {
            $category = $this->getCategoryById($categoryId);
            if ($category) {
                $categoryKeyId = $category->getRowId();
            }
        }

        if ($categoryKeyId === null) {
            return $path;
        }

        $key = $storeId . '-' . $categoryKeyId;
        if (isset($categories[$key])) {
            $path = ($categories[$key]['value'] === '1') ? (string) $categories[$key]['path'] : null;
        } elseif ($storeId !== 0) {
            $key = '0-' . $categoryKeyId;

            if (isset($categories[$key])) {
                $path = ($categories[$key]['value'] === '1') ? (string) $categories[$key]['path'] : null;
            }
        }

        return $path;
    }

    private function getCategories()
    {
        if (isset($this->activeCategories)) {
            return $this->activeCategories;
        }

        $this->activeCategories = [];

        /** @var \Magento\Catalog\Model\ResourceModel\Category $resource */
        $resource = $this->objectManager->create('\Magento\Catalog\Model\ResourceModel\Category');

        if ($attribute = $resource->getAttribute('is_active')) {
            $columnId = $this->getCorrectIdColumn();
            $key = new \Zend_Db_Expr("CONCAT(backend.store_id, '-', backend.".$columnId.")");

            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()
                                 ->from(
                                     ['backend' => $attribute->getBackendTable()],
                                     ['key' => $key, 'category.path', 'backend.value']
                                 )
                                 ->join(
                                     ['category' => $resource->getTable('catalog_category_entity')],
                                     'backend.'.$columnId.' = category.'.$columnId,
                                     []
                                 )
                                 ->where('backend.attribute_id = ?', $attribute->getAttributeId())
                                 ->order('backend.store_id')
                                 ->order('backend.'.$columnId);

            $this->activeCategories = $connection->fetchAssoc($select);
        }

        return $this->activeCategories;
    }

    public function getCategoryName($categoryId, $storeId = null)
    {
        if ($categoryId instanceof MagentoCategory) {
            $categoryId = $categoryId->getId();
        }

        if ($storeId instanceof  Store) {
            $storeId = $storeId->getId();
        }

        $categoryId = (int) $categoryId;
        $storeId = (int) $storeId;

        if (!isset($this->categoryNames)) {
            $this->categoryNames = [];

            /** @var \Magento\Catalog\Model\ResourceModel\Category $categoryModel */
            $categoryModel = $this->objectManager->create('\Magento\Catalog\Model\ResourceModel\Category');

            if ($attribute = $categoryModel->getAttribute('name')) {
                $columnId = $this->getCorrectIdColumn();
                $expression = new \Zend_Db_Expr("CONCAT(backend.store_id, '-', backend.".$columnId.")");

                $connection = $this->resourceConnection->getConnection();
                $select = $connection->select()
                                     ->from(
                                         ['backend' => $attribute->getBackendTable()],
                                         [$expression, 'backend.value']
                                     )
                                     ->join(
                                         ['category' => $categoryModel->getTable('catalog_category_entity')],
                                         'backend.'.$columnId.' = category.'.$columnId,
                                         []
                                     )
                                     ->where('backend.attribute_id = ?', $attribute->getAttributeId())
                                     ->where('category.level > ?', 1);

                $this->categoryNames = $connection->fetchPairs($select);
            }
        }

        $categoryName = null;

        $categoryKeyId = $this->getCategoryKeyId($categoryId);

        if ($categoryKeyId === null) {
            return $categoryName;
        }

        $key = $storeId . '-' . $categoryKeyId;

        if (isset($this->categoryNames[$key])) {
            // Check whether the category name is present for the specified store
            $categoryName = (string) $this->categoryNames[$key];
        } elseif ($storeId !== 0) {
            // Check whether the category name is present for the default store
            $key = '0-' . $categoryKeyId;
            if (isset($this->categoryNames[$key])) {
                $categoryName = (string) $this->categoryNames[$key];
            }
        }

        return $categoryName;
    }

    private function getCategoryKeyId($categoryId)
    {
        $categoryKeyId = $categoryId;

        if ($this->getCorrectIdColumn() === 'row_id') {
            $category = $this->getCategoryById($categoryId);
            if ($category) {
                $categoryKeyId = $category->getRowId();
            }
        }

        return $categoryKeyId;
    }

    private function getCategoryById($categoryId)
    {
        $categories = $this->getCoreCategories();

        return isset($categories[$categoryId]) ? $categories[$categoryId] : null;
    }

    public function isCategoryVisibleInMenu($categoryId, $storeId)
    {
        $key = $categoryId.' - '.$storeId;
        if (isset($this->isCategoryVisibleInMenuCache[$key])) {
            return $this->isCategoryVisibleInMenuCache[$key];
        }

        $categoryId = (int) $categoryId;

        /** @var \Magento\Catalog\Model\Category $category */
        $category = $this->objectManager->create('\Magento\Catalog\Model\Category');
        $category = $category->setStoreId($storeId)->load($categoryId);

        $this->isCategoryVisibleInMenuCache[$key] = (bool) $category->getIncludeInMenu();

        return $this->isCategoryVisibleInMenuCache[$key];
    }

    public function getCoreCategories()
    {
        if (isset($this->coreCategories)) {
            return $this->coreCategories;
        }

        $categoriesData = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Category\Collection');
        $categoriesData
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('include_in_menu', '1')
            ->addFieldToFilter('level', ['gt' => 1])
            ->addIsActiveFilter();

        $this->coreCategories = [];

        /** @var \Magento\Catalog\Model\Category $category */
        foreach ($categoriesData as $category) {
            $this->coreCategories[$category->getId()] = $category;
        }

        return $this->coreCategories;
    }

    private function getCorrectIdColumn()
    {
        if (isset($this->idColumn)) {
            return $this->idColumn;
        }

        $this->idColumn = 'entity_id';

        $edition = $this->configHelper->getMagentoEdition();
        $version = $this->configHelper->getMagentoVersion();

        if ($edition !== 'Community' && version_compare($version, '2.1.0', '>=')) {
            $this->idColumn = 'row_id';
        }

        return $this->idColumn;
    }
}
