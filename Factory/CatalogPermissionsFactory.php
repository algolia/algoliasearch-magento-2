<?php

namespace Algolia\AlgoliaSearch\Factory;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;

class CatalogPermissionsFactory
{
    private $scopeConfig;
    private $moduleManager;
    private $objectManager;

    private $categoryPermissionsCollection;
    private $productPermissionsCollection;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Manager $moduleManager,
        ObjectManagerInterface $objectManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
    }

    public function isCatalogPermissionsEnabled($storeId)
    {
        return $this->isCatalogPermissionsModuleEnabled()
            && $this->getCatalogPermissionsConfig()->isEnabled($storeId);
    }

    private function isCatalogPermissionsModuleEnabled()
    {
        return $this->moduleManager->isEnabled('Magento_CatalogPermissions');
    }

    public function getPermissionsIndexResource()
    {
        return $this->objectManager->create('\Magento\CatalogPermissions\Model\ResourceModel\Permission\Index');
    }

    public function getCatalogPermissionsHelper()
    {
        return $this->objectManager->create('\Magento\CatalogPermissions\Helper\Data');
    }

    public function getCatalogPermissionsConfig()
    {
        return $this->objectManager->create('\Magento\CatalogPermissions\App\Config');
    }

    public function getCategoryPermissionsCollection()
    {
        if (!$this->categoryPermissionsCollection) {
            /** @var \Magento\CatalogPermissions\Model\ResourceModel\Permission\Index $indexResource */
            $indexResource = $this->getPermissionsIndexResource();
            $connection = $indexResource->getConnection();

            $query = "
                SELECT category_id, GROUP_CONCAT(CONCAT(customer_group_id, '_', grant_catalog_category_view) SEPARATOR ',') AS permissions
                FROM {$indexResource->getMainTable()} 
                GROUP BY category_id;
            ";

            $this->categoryPermissionsCollection = $connection->fetchPairs($query);
        }

        return $this->categoryPermissionsCollection;
    }

    public function getProductPermissionsCollection()
    {
        if (!$this->productPermissionsCollection) {
            /** @var \Magento\CatalogPermissions\Model\ResourceModel\Permission\Index $indexResource */
            $indexResource = $this->getPermissionsIndexResource();
            $connection = $indexResource->getConnection();

            $query = "
                SELECT product_id, 
                GROUP_CONCAT(CONCAT(store_id, '_', customer_group_id, '_', grant_catalog_category_view) SEPARATOR ', ') AS permissions
                FROM {$indexResource->getTable([$indexResource->getMainTable(), 'product'])}
                GROUP BY product_id;
            ";

            $this->productPermissionsCollection = $connection->fetchPairs($query);
        }

        return $this->productPermissionsCollection;
    }
}
