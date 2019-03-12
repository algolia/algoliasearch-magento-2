<?php

namespace Algolia\AlgoliaSearch\Model\Observer\CatalogPermissions;

use Algolia\AlgoliaSearch\Factory\CatalogPermissionsFactory;
use Algolia\AlgoliaSearch\Factory\SharedCatalogFactory;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductCollectionAddPermissions implements ObserverInterface
{
    private $permissionsFactory;
    private $customerGroupCollection;
    private $sharedCatalogFactory;

    public function __construct(
        CustomerGroupCollection $customerGroupCollection,
        CatalogPermissionsFactory $permissionsFactory,
        SharedCatalogFactory $sharedCatalogFactory
    ) {
        $this->customerGroupCollection = $customerGroupCollection;
        $this->permissionsFactory = $permissionsFactory;
        $this->sharedCatalogFactory = $sharedCatalogFactory;
    }

    public function execute(Observer $observer)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        $collection = $observer->getData('collection');
        $storeId = $observer->getData('store_id');
        /** @var \Magento\Framework\DataObject $additionalData */
        $additionalData = $observer->getData('additional_data');

        if (!$this->permissionsFactory->isCatalogPermissionsEnabled($storeId)) {
            return $this;
        }

        $items = [];
        if ($additionalData->getData('items')) {
            $items = $additionalData->getData('items');
        }

        $productIds = array_flip($collection->getColumnValues('entity_id'));
        $setPermissions = [];

        $productPermissionsCollection = $this->permissionsFactory->getProductPermissionsCollection();
        if (count($productPermissionsCollection)) {
            $permissionsCollection = array_intersect_key($productPermissionsCollection, $productIds);
            foreach ($permissionsCollection as $productId => $permissions) {
                $permissions = explode(',', $permissions);
                foreach ($permissions as $permission) {
                    list ($permissionStoreId, $customerGroupId, $level) = explode('_', $permission);
                    if ($permissionStoreId == $storeId) {
                        $setPermissions[$productId]['customer_group_permission_' . $customerGroupId] = ($level == -1 ? 1 : 0);
                    }
                }
            }
        }

        if ($this->sharedCatalogFactory->isSharedCatalogEnabled($storeId)) {
            $sharedCatalogCollection = $this->sharedCatalogFactory->getSharedProductItemCollection();
            if (count($sharedCatalogCollection)) {
                $sharedCollection = array_intersect_key($sharedCatalogCollection, $productIds);
                foreach ($sharedCollection as $productId => $permissions) {
                    $permissions = explode(',', $permissions);
                    foreach ($permissions as $permission) {
                        list ($customerGroupId, $level) = explode('_', $permission);
                        $setPermissions[$productId]['shared_catalog_permission_' . $customerGroupId] = $level;
                    }
                }
            }
        }

        if (count($setPermissions)) {
            foreach ($setPermissions as $productId => $permissions) {
                if (isset($items[$productId])) {
                    $items[$productId] = array_merge($permissions, $items[$productId]);
                } else {
                    $items[$productId] = $permissions;
                }
            }
        }

        $additionalData->setData('items', $items);

        return $this;
    }
}
