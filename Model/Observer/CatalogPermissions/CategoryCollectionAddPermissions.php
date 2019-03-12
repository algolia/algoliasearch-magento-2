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

        /** @var \Magento\CatalogPermissions\Model\ResourceModel\Permission\Collection $categoryPermissionsCollection */
        $categoryPermissionsCollection = $this->permissionsFactory->getCategoryPermissionsCollection($collection->getAllIds());
        if ($this->sharedCatalogFactory->isSharedCatalogEnabled($storeId)) {
            $sharedCategoryCollection = $this->sharedCatalogFactory->getSharedCategoryCollection();
        }
        if (count($categoryPermissionsCollection)) {
            foreach ($collection as $category) {
                if (isset($categoryPermissionsCollection[$category->getId()])) {
                    $permissions = $categoryPermissionsCollection[$category->getId()];
                    $permissions = explode(',', $permissions);
                    foreach ($permissions as $permission) {
                        list ($customerGroupId, $level) = explode('_', $permission);
                        $category->setData('customer_group_permission_' . $customerGroupId, $level == -1 ? 1 : 0);
                    }
                }

                if (isset($sharedCategoryCollection) && is_array($sharedCategoryCollection)) {
                    if (isset($sharedCategoryCollection[$category->getId()])) {
                        $sharedPermissions = $sharedCategoryCollection[$category->getId()];
                        $sharedPermissions = explode(',', $sharedPermissions);
                        foreach ($sharedPermissions as $permission) {
                            list ($customerGroupId, $level) = explode('_', $permission);
                            $category->setData('shared_catalog_permission_' . $customerGroupId, $level == -1 ? 1 : 0);
                        }
                    }
                }
            }
        }

        return $this;
    }
}
