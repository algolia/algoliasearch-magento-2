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
        $storeId = $observer->getData('store');

        if (!$this->permissionsFactory->isCatalogPermissionsEnabled($storeId)) {
            return $this;
        }

        if ($permissionsIndex = $this->permissionsFactory->getPermissionsIndexResource()) {
            $select = $collection->getSelect();
            foreach ($this->customerGroupCollection as $customerGroup) {
                $customerGroupId = $customerGroup->getCustomerGroupId();
                $columnName = 'customer_group_permission_'. $customerGroupId;

                $select->joinLeft(
                    ['cgp_' . $customerGroupId => $this->permissionsFactory->getPermissionsProductTable()],
                    'e.entity_id = cgp_'. $customerGroupId .'.product_id
                        AND cgp_'. $customerGroupId . '.customer_group_id = ' . $customerGroupId
                    .' AND cgp_'. $customerGroupId .'.store_id = '. $storeId,
                    [$columnName => 'IF (cgp_'. $customerGroupId .'.grant_catalog_category_view = -1, 1, 0)']
                );

                if ($this->sharedCatalogFactory->isSharedCatalogEnabled($storeId, $customerGroupId)) {
                    $sharedResource = $this->sharedCatalogFactory->getSharedCatalogProductItemResource();
                    $columnName = 'shared_catalog_permission_'. $customerGroupId;

                    $select->joinLeft(
                        ['scp_' . $customerGroupId => $sharedResource->getMainTable()],
                        'e.sku = scp_'. $customerGroupId .'.sku 
                            AND scp_'.$customerGroupId .'.customer_group_id = ' . $customerGroupId,
                        [$columnName => 'IF (scp_'. $customerGroupId .'.sku IS NOT NULL, 1, 0)']
                    );
                }
            }
        }

        return $this;
    }
}
