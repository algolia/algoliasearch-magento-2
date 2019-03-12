<?php

namespace Algolia\AlgoliaSearch\Factory;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;

class SharedCatalogFactory
{
    const SHARED_CATALOG_ENABLED_CONFIG_PATH = 'btob/website_configuration/sharedcatalog_active';

    private $scopeConfig;
    private $moduleManager;
    private $objectManager;

    private $sharedCategoryCollection;
    private $sharedProductItemCollection;

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
        $isEnabled = $this->scopeConfig->isSetFlag(
            self::SHARED_CATALOG_ENABLED_CONFIG_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $isEnabled && $this->isSharedCatalogModuleEnabled();
    }

    private function isSharedCatalogModuleEnabled()
    {
        return $this->moduleManager->isEnabled('Magento_SharedCatalog');
    }

    public function getSharedCatalogProductItemResource()
    {
        return $this->objectManager->create('\Magento\SharedCatalog\Model\ResourceModel\ProductItem');
    }

    public function getSharedCatalogCategoryResource()
    {
        return $this->objectManager->create('\Magento\SharedCatalog\Model\ResourceModel\Permission');
    }

    public function getSharedCatalogResource()
    {
        return $this->objectManager->create('\Magento\SharedCatalog\Model\ResourceModel\SharedCatalog');
    }

    public function getSharedCategoryCollection()
    {
        if (!$this->sharedCategoryCollection) {
            $indexResource = $this->getSharedCatalogCategoryResource();
            $connection = $indexResource->getConnection();

            $query = "
                SELECT category_id, GROUP_CONCAT(CONCAT(customer_group_id, '_', permission) SEPARATOR ',') AS permissions
                FROM {$indexResource->getMainTable()} 
                WHERE customer_group_id IN (SELECT customer_group_id FROM shared_catalog) 
                GROUP BY category_id;
            ";

            $this->sharedCategoryCollection = $connection->fetchPairs($query);
        }

        return $this->sharedCategoryCollection;
    }

    public function getSharedProductItemCollection()
    {
        if (!$this->sharedProductItemCollection) {
            /** @var \Magento\SharedCatalog\Model\ResourceModel\ProductItem $indexResource */
            $indexResource = $this->getSharedCatalogProductItemResource();
            $connection = $indexResource->getConnection();

            $query = "
                SELECT cpe.entity_id, GROUP_CONCAT(pi.customer_group_id SEPARATOR ',') as groups
                FROM {$indexResource->getMainTable()} as pi
                INNER JOIN {$this->getSharedCatalogResource()->getMainTable()} AS sc
                ON sc.customer_group_id = pi.customer_group_id
                LEFT JOIN {$indexResource->getTable('catalog_product_entity')} AS cpe
                ON pi.sku = cpe.sku
                GROUP BY pi.sku
            ";

            $productItems = $connection->fetchPairs($query);
            $groups = $this->getSharedCatalogGroups();

            foreach ($productItems as $productId => $permissions) {
                $permissions = explode(',', $permissions);
                $finalPermissions = [];
                foreach ($groups as $groupId) {
                    $finalPermissions[] = $groupId . '_' . (in_array($groupId, $permissions) ? '1' : '0');
                }
                $productItems[$productId] = implode(',', $finalPermissions);
            }
            $this->sharedProductItemCollection = $productItems;
        }

        return $this->sharedProductItemCollection;
    }

    public function getSharedCatalogGroups()
    {
        /** @var \Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection $sharedCatalog */
        $sharedCatalog = $this->objectManager->create('\Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection');

        return $sharedCatalog->getColumnValues('customer_group_id');
    }
}
