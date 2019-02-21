<?php

namespace Algolia\AlgoliaSearch\Model\Observer\CatalogPermissions;

use Algolia\AlgoliaSearch\Factory\CatalogPermissionsFactory;
use Algolia\AlgoliaSearch\Factory\SharedCatalogFactory;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CategoryPermissions implements ObserverInterface
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
        /** @var \Magento\Framework\DataObject $transport */
        $transport = $observer->getData('categoryObject');
        /** @var \Magento\Catalog\Model\Category $category */
        $category = $observer->getData('category');
        $storeId = $category->getStoreId();

        if (!$this->permissionsFactory->isCatalogPermissionsEnabled($storeId)) {
            return $this;
        }

        /** @var \Magento\CatalogPermissions\Model\Permission\Index $permissionsIndex */
        if ($permissionsIndex = $this->permissionsFactory->getPermissionsIndex()) {
            $permissions = [];

            $collection = $this->customerGroupCollection;
            foreach ($collection as $customerGroup) {
                $customerGroupId = $customerGroup->getCustomerGroupId();
                $restrictedIds = $permissionsIndex->getRestrictedCategoryIds($customerGroupId, null);

                $permissions['customer_group_' . $customerGroupId] = in_array($category->getId(), $restrictedIds) ? 0 : 1;
            }

            if ($this->sharedCatalogFactory->isSharedCatalogEnabled($storeId)) {
                /** @var \Magento\SharedCatalog\Model\ResourceModel\ProductItem $sharedCatalog */
                if (!$this->sharedCatalogFactory->isCategoryInSharedCatalogForCustomerGroup($category, $customerGroupId)) {
                    $permissions['customer_group_' . $customerGroupId] = 0;
                }
            }

            $transport->setData('catalog_permissions', $permissions);
        }

        return $this;
    }
}
