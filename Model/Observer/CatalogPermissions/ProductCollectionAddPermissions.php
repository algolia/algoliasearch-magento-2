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
        /** @var \Algolia\AlgoliaSearch\Helper\ProductDataArray $additionalData */
        $additionalData = $observer->getData('additional_data');

        if (!$this->permissionsFactory->isCatalogPermissionsEnabled($storeId)) {
            return $this;
        }

        $productIds = array_flip($collection->getColumnValues('entity_id'));

        $this->addProductPermissionsData($additionalData, $productIds, $storeId);
        if ($this->sharedCatalogFactory->isSharedCatalogEnabled($storeId)) {
            $this->addSharedCatalogData($additionalData, $productIds);
        }

        return $this;
    }

    /**
     * @param $additionalData \Algolia\AlgoliaSearch\Helper\ProductDataArray
     * @param $productIds
     * @param $storeId
     */
    protected function addProductPermissionsData($additionalData, $productIds, $storeId)
    {
        $productPermissionsCollection = $this->permissionsFactory->getProductPermissionsCollection();
        if (count($productPermissionsCollection) === 0) {
            return;
        }

        $permissionsCollection = array_intersect_key($productPermissionsCollection, $productIds);
        $catalogPermissionsHelper = $this->permissionsFactory->getCatalogPermissionsHelper();
        foreach ($permissionsCollection as $productId => $permissions) {
            $permissions = explode(',', $permissions);
            foreach ($permissions as $permission) {
                list($permissionStoreId, $customerGroupId, $level) = explode('_', $permission);
                if ($permissionStoreId == $storeId) {
                    $additionalData->addProductData($productId, [
                        'customer_group_permission_' . $customerGroupId => (($level == -2 || $level != -1
                        && !$catalogPermissionsHelper->isAllowedCategoryView()) ? 0 : 1),
                    ]);
                }
            }
        }
    }

    /**
     * @param $additionalData \Algolia\AlgoliaSearch\Helper\ProductDataArray
     * @param $productIds
     */
    protected function addSharedCatalogData($additionalData, $productIds)
    {
        $sharedCatalogCollection = $this->sharedCatalogFactory->getSharedProductItemCollection();
        if (count($sharedCatalogCollection) === 0) {
            return;
        }

        $sharedCollection = array_intersect_key($sharedCatalogCollection, $productIds);
        foreach ($sharedCollection as $productId => $permissions) {
            $permissions = explode(',', $permissions);
            foreach ($permissions as $permission) {
                list($customerGroupId, $level) = explode('_', $permission);
                $additionalData->addProductData($productId, [
                    'shared_catalog_permission_' . $customerGroupId => $level,
                ]);
            }
        }
    }
}
