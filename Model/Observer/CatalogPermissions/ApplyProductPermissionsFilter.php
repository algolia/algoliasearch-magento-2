<?php

namespace Algolia\AlgoliaSearch\Model\Observer\CatalogPermissions;

use Algolia\AlgoliaSearch\Factory\CatalogPermissionsFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManager;

class ApplyProductPermissionsFilter implements ObserverInterface
{
    private $permissionsFactory;
    private $storeManager;

    public function __construct(
        CatalogPermissionsFactory $permissionsFactory,
        StoreManager $storeManager
    ) {
        $this->permissionsFactory = $permissionsFactory;
        $this->storeManager = $storeManager;
    }

    public function execute(Observer $observer)
    {
        $storeId = $this->storeManager->getStore()->getId();
        if (!$this->permissionsFactory->isCatalogPermissionsEnabled($storeId)) {
            return $this;
        }

        /** @var \Magento\Framework\DataObject $transport */
        $transport = $observer->getData('filter_object');
        $customerGroupId = $observer->getData('customer_group_id');

        $transport->setData('catalog_permissions', 'catalog_permissions.customer_group_' . $customerGroupId . ' != 0');

        return $this;
    }
}
