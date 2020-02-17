<?php

namespace Algolia\AlgoliaSearch\Factory;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;

class SharedCatalogFactory
{
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
        return $this->isSharedCatalogModuleEnabled()
            && $this->getSharedCatalogConfig()->isActive(
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId
            );
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

    public function getSharedCatalogConfig()
    {
        return $this->objectManager->create('\Magento\SharedCatalog\Model\Config');
    }

    private function getSharedCatalogCustomerGroups()
    {
        $sharedCatalogResource = $this->getSharedCatalogResource();
        $connection = $sharedCatalogResource->getConnection();

        $select = $connection->select()
            ->from(($sharedCatalogResource->getMainTable()), ['customer_group_id']);

        return $connection->fetchAll($select);
    }

    public function getSharedCategoryCollection()
    {
        if (!$this->sharedCategoryCollection) {
            $indexResource = $this->getSharedCatalogCategoryResource();
            $connection = $indexResource->getConnection();

            $select = $connection->select()
                ->from($indexResource->getMainTable(), [])
                ->columns('category_id')
                ->columns(['permissions' => new \Zend_Db_Expr("GROUP_CONCAT(CONCAT(customer_group_id, '_', permission) SEPARATOR ',')")])
                ->where('customer_group_id IN (?)', $this->getSharedCatalogCustomerGroups())
                ->group('category_id');

            $this->sharedCategoryCollection = $connection->fetchPairs($select);
        }

        return $this->sharedCategoryCollection;
    }

    public function getSharedProductItemCollection()
    {
        if (!$this->sharedProductItemCollection) {
            /** @var \Magento\SharedCatalog\Model\ResourceModel\ProductItem $indexResource */
            $indexResource = $this->getSharedCatalogProductItemResource();
            $connection = $indexResource->getConnection();

            $select = $connection->select()
                ->from(['pi' => $indexResource->getMainTable()], [])
                ->columns('cpe.entity_id')
                ->columns(['groups' => new \Zend_Db_Expr("GROUP_CONCAT(pi.customer_group_id SEPARATOR ',')")])
                ->joinInner(
                    ['sc' => $this->getSharedCatalogResource()->getMainTable()],
                    'sc.customer_group_id = pi.customer_group_id',
                    []
                )
                ->joinLeft(
                    ['cpe' => $indexResource->getTable('catalog_product_entity')],
                    'pi.sku = cpe.sku',
                    []
                )->group('pi.sku');

            $productItems = $connection->fetchPairs($select);
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
