<?php

namespace Algolia\AlgoliaSearch\Factory;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;

class SharedCatalogFactory
{
    const SHARED_CATALOG_ENABLED_CONFIG_PATH = 'btob/website_configuration/sharedcatalog_active';

    private $scopeConfig;
    private $moduleManager;
    private $objectManager;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Manager $moduleManager,
        ObjectManagerInterface $objectManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
    }

    public function isSharedCatalogEnabled($storeId)
    {
        return $this->scopeConfig->isSetFlag(
            self::SHARED_CATALOG_ENABLED_CONFIG_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isSharedCatalogModuleEnabled()
    {
        return $this->moduleManager->isEnabled('Magento_SharedCatalog');
    }

    public function getSharedCatalogProductItemResource()
    {
        return $this->isSharedCatalogModuleEnabled() ?
            $this->objectManager->create('\Magento\SharedCatalog\Model\ResourceModel\ProductItem') : false;
    }

    public function getSharedCatalogCategoryResource()
    {
        return $this->isSharedCatalogModuleEnabled() ?
            $this->objectManager->create('\Magento\SharedCatalog\Model\ResourceModel\Permission') : false;
    }

    public function isProductInSharedCatalogForCustomerGroup(Product $product, $customerGroupId)
    {
        /** @var \Magento\SharedCatalog\Model\ResourceModel\ProductItem $resourceModel */
        $resourceModel = $this->getSharedCatalogProductItemResource();
        $connection = $resourceModel->getConnection();

        $select = $connection->select()->from(
            ['shared_catalog_product' => $resourceModel->getMainTable()]
        )->where(
            'customer_group_id = ?',
            $customerGroupId
        )->where(
            'sku = ?',
            $product->getSku()
        );

        $shared = $connection->fetchRow($select);

        return $shared ? true : false;
    }

    public function isCategoryInSharedCatalogForCustomerGroup(Category $category, $customerGroupId)
    {
        /** @var \Magento\SharedCatalog\Model\ResourceModel\Permission $resourceModel */
        $resourceModel = $this->getSharedCatalogCategoryResource();
        $connection = $resourceModel->getConnection();

        $select = $connection->select()->from(
            ['shared_catalog_category' => $resourceModel->getMainTable()]
        )->where(
            'customer_group_id = ?',
            $customerGroupId
        )->where(
            'category_id = ?',
            $category->getId()
        )->where(
            'permission = ' . \Magento\CatalogPermissions\Model\Permission::PERMISSION_ALLOW
        );

        $shared = $connection->fetchRow($select);

        return $shared ? true : false;
    }
}
