<?php

namespace Algolia\AlgoliaSearch\Model\Observer\CatalogPermissions;

use Algolia\AlgoliaSearch\Factory\CatalogPermissionsFactory;
use Algolia\AlgoliaSearch\Factory\SharedCatalogFactory;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductPermissions implements ObserverInterface
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
        $transport = $observer->getData('custom_data');
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getData('productObject');
        $storeId = $product->getStoreId();

        if (!$this->permissionsFactory->isCatalogPermissionsEnabled($storeId)) {
            return $this;
        }

        $permissions = [];

        /** @var \Magento\CatalogPermissions\Model\Permission\Index $permissionsIndex */
        if ($permissionsIndex = $this->permissionsFactory->getPermissionsIndex()) {
            $permissionsHelper = $this->permissionsFactory->getPermissionsHelper();

            $collection = $this->customerGroupCollection;
            foreach ($collection as $customerGroup) {
                $customerGroupId = $customerGroup->getCustomerGroupId();
                $permissionsIndex->addIndexToProduct($product, $customerGroupId);

                $permissions['customer_group_' . $customerGroupId] = 1;

                if ($product->getData('grant_catalog_category_view') == -2
                    || $product->getData('grant_catalog_category_view') != -1
                    && !$permissionsHelper->isAllowedCategoryView($storeId, $customerGroupId)
                ) {
                    $permissions['customer_group_' . $customerGroupId] = 0;
                }

                if ($this->sharedCatalogFactory->isSharedCatalogEnabled($storeId)) {
                    /** @var \Magento\SharedCatalog\Model\ResourceModel\ProductItem $sharedCatalog */
                    if (!$this->sharedCatalogFactory->isProductInSharedCatalogForCustomerGroup($product, $customerGroupId)) {
                        $permissions['customer_group_' . $customerGroupId] = 0;
                    }
                }
            }
        }

        if (count($permissions)) {
            $transport->setData('catalog_permissions', $permissions);
        }

        return $this;
    }
}
