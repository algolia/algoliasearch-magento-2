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

    private $sharedCatalog;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Manager $moduleManager,
        ObjectManagerInterface $objectManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
    }

    public function isSharedCatalogEnabled($storeId, $customerGroupId)
    {
        $isEnabled = $this->scopeConfig->isSetFlag(
            self::SHARED_CATALOG_ENABLED_CONFIG_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (!$isEnabled || !$this->isSharedCatalogModuleEnabled()) {
            return false;
        }

        $sharedCollection = $this->getSharedCatalogCollection();
        $items = $sharedCollection->getItemsByColumnValue('customer_group_id', $customerGroupId);

        return count($items) > 0 ? true : false;
    }

    private function isSharedCatalogModuleEnabled()
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

    private function getSharedCatalogCollection()
    {
        if (!isset($this->sharedCatalog)) {
            $this->sharedCatalog = $this->objectManager->create('\Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection');
        }

        return $this->sharedCatalog;
    }
}
