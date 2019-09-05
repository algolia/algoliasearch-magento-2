<?php

namespace Algolia\AlgoliaSearch\Model\Observer\CatalogPermissions;

use Algolia\AlgoliaSearch\Factory\CatalogPermissionsFactory;
use Algolia\AlgoliaSearch\Factory\SharedCatalogFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManager;

class ApplyProductPermissionsFilter implements ObserverInterface
{
    /** @var CatalogPermissionsFactory */
    private $permissionsFactory;

    /** @var SharedCatalogFactory */
    private $sharedCatalogFactory;

    /** @var StoreManager */
    private $storeManager;

    /**
     * @param CatalogPermissionsFactory $permissionsFactory
     * @param SharedCatalogFactory $sharedCatalogFactory
     * @param StoreManager $storeManager
     */
    public function __construct(
        CatalogPermissionsFactory $permissionsFactory,
        SharedCatalogFactory $sharedCatalogFactory,
        StoreManager $storeManager
    ) {
        $this->permissionsFactory = $permissionsFactory;
        $this->sharedCatalogFactory = $sharedCatalogFactory;
        $this->storeManager = $storeManager;
    }

    public function execute(Observer $observer)
    {
        $storeId = $this->storeManager->getStore()->getId();
        if (!$this->permissionsFactory->isCatalogPermissionsEnabled($storeId)
            || ($this->permissionsFactory->getCatalogPermissionsHelper()->isAllowedCategoryView($storeId)
                && !$this->sharedCatalogFactory->isSharedCatalogEnabled($storeId))
        ) {
            return $this;
        }

        /** @var \Magento\Framework\DataObject $transport */
        $transport = $observer->getData('filter_object');
        $customerGroupId = $observer->getData('customer_group_id');

        $transport->setData('catalog_permissions', 'catalog_permissions.customer_group_' . $customerGroupId . ' != 0');

        return $this;
    }
}
