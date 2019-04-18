<?php

namespace Algolia\AlgoliaSearch\Model\Observer\CatalogPermissions;

use Algolia\AlgoliaSearch\Factory\CatalogPermissionsFactory;
use Algolia\AlgoliaSearch\Factory\SharedCatalogFactory;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CategoryCollectionAddPermissions implements ObserverInterface
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
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = $observer->getData('collection');
        $storeId = $observer->getData('store');

        if (!$this->permissionsFactory->isCatalogPermissionsEnabled($storeId)) {
            return $this;
        }

        $categoryIds = array_flip($collection->getColumnValues('entity_id'));

        $this->addCatalogPermissionsData($collection, $categoryIds);
        $this->addSharedCatalogData($collection, $categoryIds, $storeId);

        return $this;
    }

    protected function addCatalogPermissionsData($collection, $categoryIds)
    {
        $categoryPermissionsCollection = $this->permissionsFactory->getCategoryPermissionsCollection();
        $permissionsCollection = array_intersect_key($categoryPermissionsCollection, $categoryIds);
        if (count($permissionsCollection) === 0) {
            return;
        }

        $catalogPermissionsHelper = $this->permissionsFactory->getCatalogPermissionsHelper();
        foreach ($permissionsCollection as $categoryId => $permissions) {
            $permissions = explode(',', $permissions);
            foreach ($permissions as $permission) {
                list($customerGroupId, $level) = explode('_', $permission);
                if ($category = $collection->getItemById($categoryId)) {
                    $category->setData('customer_group_permission_' . $customerGroupId, (($level == -2 || $level != -1
                        && !$catalogPermissionsHelper->isAllowedCategoryView()) ? 0 : 1));
                }
            }
        }
    }

    protected function addSharedCatalogData($collection, $categoryIds, $storeId)
    {
        if (!$this->sharedCatalogFactory->isSharedCatalogEnabled($storeId)) {
            return;
        }

        $sharedCategoryCollection = $this->sharedCatalogFactory->getSharedCategoryCollection();
        $sharedCollection = array_intersect_key($sharedCategoryCollection, $categoryIds);

        if (count($sharedCollection) === 0) {
            return;
        }

        foreach ($sharedCollection as $categoryId => $permissions) {
            $permissions = explode(',', $permissions);
            foreach ($permissions as $permission) {
                list($customerGroupId, $level) = explode('_', $permission);
                if ($category = $collection->getItemById($categoryId)) {
                    $category->setData('shared_catalog_permission_' . $customerGroupId, $level == -1 ? 1 : 0);
                }
            }
        }
    }
}
