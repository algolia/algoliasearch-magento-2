<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups;

use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Customer\Api\GroupExcludedWebsiteRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupResourceCollection;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;

class CustomerGroupsManager
{
    /**
     * @var array<int, CustomerGroupResourceCollection>
     */
    protected array $customerGroupsByStore = [];

    public function __construct(
        protected ConfigHelper $configHelper,
        protected CollectionFactory $customerGroupCollectionFactory,
        protected GroupExcludedWebsiteRepositoryInterface $groupExcludedWebsiteRepository,
    ) {}

    public function getCustomerGroups(PricingContextInterface $pricingContext): CustomerGroupResourceCollection
    {
        $storeId = $pricingContext->getStore()->getId();

        if (!isset($this->customerGroupsByStore[$storeId])) {
            $this->customerGroupsByStore[$storeId] = $this->getCustomerGroupCollection();
            $this->filterCustomerGroups($pricingContext, $this->customerGroupsByStore[$storeId]);
        }

        return $this->customerGroupsByStore[$storeId];
    }

    protected function filterCustomerGroups(
        PricingContextInterface $pricingContext,
        CustomerGroupResourceCollection $collection
    ): void
    {
        if (!$pricingContext->areCustomerGroupsEnabled()) {
            $collection->addFieldToFilter('main_table.customer_group_id', 0);
            return;
        }

        $excludedGroups = [];
        foreach ($collection as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $excludedWebsites = $this->getCustomerGroupExcludedWebsites($groupId);
            if (in_array($pricingContext->getStore()->getWebsiteId(), $excludedWebsites)) {
                $excludedGroups[] = $groupId;
            }
        }

        if (count($excludedGroups) > 0) {
            $collection->addFieldToFilter('main_table.customer_group_id', ['nin' => $excludedGroups]);
            $collection->clear();
        }
    }

    public function getCustomerGroupCollection(): CustomerGroupResourceCollection
    {
        return $this->customerGroupCollectionFactory->create();
    }

    public function getCustomerGroupExcludedWebsites(int $groupId): array
    {
        return $this->groupExcludedWebsiteRepository->getCustomerGroupExcludedWebsites($groupId);
    }
}
