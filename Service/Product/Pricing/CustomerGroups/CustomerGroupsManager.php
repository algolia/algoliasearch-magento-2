<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups;

use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Customer\Api\GroupExcludedWebsiteRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupResourceCollection;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;

class CustomerGroupsManager
{
    protected ?CustomerGroupResourceCollection $customerGroups;
    protected bool $hasBeenFiltered = false;

    public function __construct(
        protected ConfigHelper $configHelper,
        protected CollectionFactory $customerGroupCollectionFactory,
        protected GroupExcludedWebsiteRepositoryInterface $groupExcludedWebsiteRepository,
    ) {
        $this->customerGroups = $this->getCustomerGroupCollection();
    }

    public function getCustomerGroups(PricingContextInterface $pricingContext): CustomerGroupResourceCollection
    {
        if (!$this->hasBeenFiltered) {
            $this->filterCustomerGroups($pricingContext);
            $this->hasBeenFiltered = true;
        }

        return $this->customerGroups;
    }

    protected function filterCustomerGroups(PricingContextInterface $pricingContext): void
    {
        if (!$pricingContext->areCustomerGroupsEnabled()) {
            $this->customerGroups->addFieldToFilter('main_table.customer_group_id', 0);
            return;
        }

        $excludedGroups = [];
        foreach ($this->customerGroups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $excludedWebsites = $this->getCustomerGroupExcludedWebsites($groupId);
            if (in_array($pricingContext->getStore()->getWebsiteId(), $excludedWebsites)) {
                $excludedGroups[] = $groupId;
            }
        }

        if (count($excludedGroups) > 0) {
            $this->customerGroups->addFieldToFilter('main_table.customer_group_id', ['nin' => $excludedGroups]);
            $this->customerGroups->clear();
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
