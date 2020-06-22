<?php

namespace Algolia\AlgoliaSearch\Model\Observer\CatalogPermissions;

use Algolia\AlgoliaSearch\Factory\CatalogPermissionsFactory;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductPermissions implements ObserverInterface
{
    private $permissionsFactory;
    private $customerGroupCollection;

    public function __construct(
        CustomerGroupCollection $customerGroupCollection,
        CatalogPermissionsFactory $permissionsFactory
    ) {
        $this->customerGroupCollection = $customerGroupCollection;
        $this->permissionsFactory = $permissionsFactory;
    }

    public function execute(Observer $observer)
    {
        /** @var \Magento\Framework\DataObject $transport */
        $transport = $observer->getData('custom_data');
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getData('productObject');
        $storeId = $product->getStoreId();

        if (!$this->permissionsFactory->isCatalogPermissionsEnabled($storeId)) {
            return $this;
        }

        $permissions = [];
        $collection = $this->customerGroupCollection;

        foreach ($collection as $customerGroup) {
            $customerGroupId = $customerGroup->getCustomerGroupId();

            $isVisible  = (int) $this->permissionsFactory->getCatalogPermissionsHelper()->isAllowedCategoryView($storeId, $customerGroupId);
            if (!is_null($product->getData('shared_catalog_permission_' . $customerGroupId))) {
                $isVisible = (int) $product->getData('shared_catalog_permission_' . $customerGroupId);
            } elseif (!is_null($product->getData('customer_group_permission_' . $customerGroupId))) {
                $isVisible = (int) $product->getData('customer_group_permission_' . $customerGroupId);
            }

            $permissions['customer_group_' . $customerGroupId] = $isVisible;
        }

        if (count($permissions)) {
            $transport->setData('catalog_permissions', $permissions);
        }

        return $this;
    }
}
