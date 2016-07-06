<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Logger;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Helper\Stock;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Directory\Model\Currency;
use Magento\Directory\Helper\Data as CurrencyDirectory;
use Magento\Directory\Model\Currency as CurrencyHelper;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Url;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Tax\Helper\Data;

abstract class BaseHelper
{
    protected $config;
    protected $logger;
    protected $algoliaHelper;
    protected $eavConfig;

    protected $storeManager;
    protected $eventManager;
    protected $currencyManager;
    protected $taxHelper;
    protected $visibility;

    protected static $_activeCategories;
    protected static $_categoryNames;
    protected $stock;
    protected $stockRegistry;
    protected $currencyHelper;
    protected $currencyDirectory;
    protected $catalogHelper;
    protected $queryResource;
    protected $filterProvider;

    protected $storeUrls;

    abstract protected function getIndexNameSuffix();

    public function __construct(Config $eavConfig,
                                ConfigHelper $configHelper,
                                AlgoliaHelper $algoliaHelper,
                                Logger $logger,
                                StoreManagerInterface $storeManager,
                                ManagerInterface $eventManager,
                                Visibility $visibility,
                                Stock $stock,
                                Data $taxHelper,
                                StockRegistryInterface $stockRegistry,
                                CurrencyDirectory $currencyDirectory,
                                CurrencyHelper $currencyHelper,
                                ObjectManagerInterface $objectManager,
                                CatalogHelper $catalogHelper,
                                ResourceConnection $queryResource,
                                Currency $currencyManager,
                                FilterProvider $filterProvider)
    {
        $this->eavConfig = $eavConfig;
        $this->config = $configHelper;
        $this->algoliaHelper = $algoliaHelper;
        $this->logger = $logger;

        $this->storeManager = $storeManager;
        $this->eventManager = $eventManager;
        $this->currencyManager = $currencyManager;
        $this->stockRegistry = $stockRegistry;
        $this->visibility = $visibility;
        $this->stock = $stock;
        $this->taxHelper = $taxHelper;
        $this->currencyHelper = $currencyHelper;
        $this->currencyDirectory = $currencyDirectory;
        $this->objectManager = $objectManager;
        $this->catalogHelper = $catalogHelper;
        $this->queryResource = $queryResource;
        $this->filterProvider = $filterProvider;
    }

    public function getBaseIndexName($storeId = null)
    {
        return (string) $this->config->getIndexPrefix($storeId).$this->storeManager->getStore($storeId)->getCode();
    }

    public function getIndexName($storeId = null, $tmp = false)
    {
        return (string) $this->getBaseIndexName($storeId).$this->getIndexNameSuffix().($tmp ? '_tmp' : '');
    }

    protected function try_cast($value)
    {
        if (is_numeric($value) && floatval($value) == floatval(intval($value))) {
            return intval($value);
        }

        if (is_numeric($value)) {
            return floatval($value);
        }

        return $value;
    }

    protected function castProductObject(&$productData)
    {
        foreach ($productData as $key => &$data) {
            $data = $this->try_cast($data);

            if (is_array($data) === false) {
                $data = explode('|', $data);

                if (count($data) == 1) {
                    $data = $data[0];
                    $data = $this->try_cast($data);
                } else {
                    foreach ($data as &$element) {
                        $element = $this->try_cast($element);
                    }
                }
            }
        }
    }

    protected function strip($s)
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        $s = preg_replace('/&nbsp;/', ' ', $s);
        $s = preg_replace('!\s+!', ' ', $s);

        return trim(strip_tags($s));
    }

    public function isCategoryActive($categoryId, $storeId = null)
    {
        $storeId = intval($storeId);
        $categoryId = intval($categoryId);

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

    public function getCategoryPath($categoryId, $storeId = null)
    {
        $categories = $this->getCategories();
        $storeId = intval($storeId);
        $categoryId = intval($categoryId);
        $path = null;
        $key = $storeId.'-'.$categoryId;

        if (isset($categories[$key])) {
            $path = ($categories[$key]['value'] == 1) ? strval($categories[$key]['path']) : null;
        } elseif ($storeId !== 0) {
            $key = '0-'.$categoryId;

            if (isset($categories[$key])) {
                $path = ($categories[$key]['value'] == 1) ? strval($categories[$key]['path']) : null;
            }
        }

        return $path;
    }

    public function getCategories()
    {
        if (is_null(self::$_activeCategories)) {
            self::$_activeCategories = [];

            /** @var \Magento\Catalog\Model\ResourceModel\Category $resource */
            $resource = $this->objectManager->create('\Magento\Catalog\Model\ResourceModel\Category');

            if ($attribute = $resource->getAttribute('is_active')) {
                $connection = $this->queryResource->getConnection();
                $select = $connection->select()
                    ->from(['backend' => $attribute->getBackendTable()], ['key' => new \Zend_Db_Expr("CONCAT(backend.store_id, '-', backend.entity_id)"), 'category.path', 'backend.value'])
                    ->join(['category' => $resource->getTable('catalog_category_entity')], 'backend.entity_id = category.entity_id', [])
                    ->where('backend.attribute_id = ?', $attribute->getAttributeId())
                    ->order('backend.store_id')
                    ->order('backend.entity_id');

                self::$_activeCategories = $connection->fetchAssoc($select);
            }
        }

        return self::$_activeCategories;
    }

    public function getCategoryName($categoryId, $storeId = null)
    {
        if ($categoryId instanceof \Magento\Catalog\Model\Category) {
            $categoryId = $categoryId->getId();
        }

        if ($storeId instanceof  \Magento\Store\Model\Store) {
            $storeId = $storeId->getId();
        }

        $categoryId = intval($categoryId);
        $storeId = intval($storeId);

        if (is_null(self::$_categoryNames)) {
            self::$_categoryNames = [];

            /** @var \Magento\Catalog\Model\ResourceModel\Category $categoryModel */
            $categoryModel = $this->objectManager->create('\Magento\Catalog\Model\ResourceModel\Category');

            if ($attribute = $categoryModel->getAttribute('name')) {
                $connection = $this->queryResource->getConnection();

                $select = $connection->select()
                    ->from(['backend' => $attribute->getBackendTable()], [new \Zend_Db_Expr("CONCAT(backend.store_id, '-', backend.entity_id)"), 'backend.value'])
                    ->join(['category' => $categoryModel->getTable('catalog_category_entity')], 'backend.entity_id = category.entity_id', [])
                    ->where('backend.attribute_id = ?', $attribute->getAttributeId())
                    ->where('category.level > ?', 1);

                self::$_categoryNames = $connection->fetchPairs($select);
            }
        }

        $categoryName = null;

        $key = $storeId.'-'.$categoryId;

        if (isset(self::$_categoryNames[$key])) {
            // Check whether the category name is present for the specified store

            $categoryName = strval(self::$_categoryNames[$key]);
        } elseif ($storeId != 0) {
            // Check whether the category name is present for the default store

            $key = '0-'.$categoryId;

            if (isset(self::$_categoryNames[$key])) {
                $categoryName = strval(self::$_categoryNames[$key]);
            }
        }

        return $categoryName;
    }

    public function getStores($store_id)
    {
        $store_ids = [];

        if ($store_id == null) {
            foreach ($this->storeManager->getStores() as $store) {
                if ($this->config->isEnabledBackEnd($store->getId()) === false) {
                    continue;
                }

                if ($store->getIsActive()) {
                    $store_ids[] = $store->getId();
                }
            }
        } else {
            $store_ids = [$store_id];
        }

        return $store_ids;
    }

    /**
     * @param $store_id
     *
     * @return Url
     */
    public function getStoreUrl($store_id)
    {
        if ($this->storeUrls == null) {
            $this->storeUrls = [];
            $storeIds = $this->getStores(null);

            foreach ($storeIds as $storeId) {
                // ObjectManager used instead of UrlFactory because UrlFactory will return UrlInterface which
                // may cause a backend Url object to be returned
                $url = $this->objectManager->create('Magento\Framework\Url');
                $url->setStore($storeId);
                $this->storeUrls[$storeId] = $url;
            }
        }

        if (array_key_exists($store_id, $this->storeUrls)) {
            return $this->storeUrls[$store_id];
        }

        return;
    }
}
