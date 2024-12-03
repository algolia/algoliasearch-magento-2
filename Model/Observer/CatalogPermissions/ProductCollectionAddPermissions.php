<?php

namespace Algolia\AlgoliaSearch\Model\Observer\CatalogPermissions;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Factory\CatalogPermissionsFactory;
use Algolia\AlgoliaSearch\Factory\SharedCatalogFactory;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductCollectionAddPermissions implements ObserverInterface
{
    public function __construct(
        protected CustomerGroupCollection   $customerGroupCollection,
        protected CatalogPermissionsFactory $permissionsFactory,
        protected SharedCatalogFactory      $sharedCatalogFactory,
        protected DiagnosticsLogger         $diag
    )
    { }

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
     * @throws DiagnosticsException
     */
    protected function addProductPermissionsData($additionalData, $productIds, $storeId)
    {
        $this->diag->startProfiling(__METHOD__);
        $productPermissionsCollection = $this->permissionsFactory->getProductPermissionsCollection();
        if (count($productPermissionsCollection) === 0) {
            $this->diag->stopProfiling(__METHOD__);
            return;
        }

        $permissionsCollection = array_intersect_key($productPermissionsCollection, $productIds);
        $catalogPermissionsHelper = $this->permissionsFactory->getCatalogPermissionsHelper();
        foreach ($permissionsCollection as $productId => $permissions) {
            $permissions = explode(',', $permissions);
            foreach ($permissions as $permission) {
                $permission = explode('_', $permission);
                if (count($permission) < 3) { // prevent undefined
                    continue;
                }
                list($permissionStoreId, $customerGroupId, $level) = $permission;
                if ($permissionStoreId == $storeId) {
                    $additionalData->addProductData($productId, [
                        'customer_group_permission_' . $customerGroupId => ($level == -2 ? 0 : 1),
                    ]);
                }
            }
        }
        $this->diag->stopProfiling(__METHOD__);
    }

    /**
     * @param $additionalData \Algolia\AlgoliaSearch\Helper\ProductDataArray
     * @param $productIds
     * @throws DiagnosticsException
     */
    protected function addSharedCatalogData($additionalData, $productIds)
    {
        $this->diag->startProfiling(__METHOD__);
        $sharedCatalogCollection = $this->sharedCatalogFactory->getSharedProductItemCollection();
        if (count($sharedCatalogCollection) === 0) {
            $this->diag->stopProfiling(__METHOD__);
            return;
        }

        $sharedCollection = array_intersect_key($sharedCatalogCollection, $productIds);
        foreach ($sharedCollection as $productId => $permissions) {
            $permissions = explode(',', $permissions);
            foreach ($permissions as $permission) {
                $permission = explode('_', $permission);
                if (count($permission) < 2) { // prevent undefined
                    continue;
                }
                list($customerGroupId, $level) = $permission;
                $additionalData->addProductData($productId, [
                    'shared_catalog_permission_' . $customerGroupId => $level,
                ]);
            }
        }
        $this->diag->stopProfiling(__METHOD__);
    }
}
