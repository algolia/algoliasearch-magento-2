<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product\Pricing\CustomerGroups;

use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups\CustomerGroupsManager;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Customer\Api\GroupExcludedWebsiteRepositoryInterface;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupResourceCollection;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups\CustomerGroupsManager
 */
class CustomerGroupsManagerTest extends TestCase
{
    /**
     * Create the object under test with optional dependency overrides
     */
    protected function createObjectToTest(
        ?ConfigHelper $configHelper = null,
        ?CollectionFactory $customerGroupCollectionFactory = null,
        ?GroupExcludedWebsiteRepositoryInterface $groupExcludedWebsiteRepository = null
    ): CustomerGroupsManager {
        $collection = $this->createStub(CustomerGroupResourceCollection::class);

        $defaultFactory = $this->createStub(CollectionFactory::class);
        $defaultFactory->method('create')->willReturn($collection);

        return new CustomerGroupsManager(
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $customerGroupCollectionFactory ?? $defaultFactory,
            $groupExcludedWebsiteRepository ?? $this->createStub(GroupExcludedWebsiteRepositoryInterface::class)
        );
    }

    /**
     * Test that getCustomerGroups filters on first call for a store
     */
    public function testGetCustomerGroupsFiltersOnFirstCall(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('areCustomerGroupsEnabled')->willReturn(false);
        $pricingContext->method('getStore')->willReturn($store);

        $collection = $this->createMock(CustomerGroupResourceCollection::class);

        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        // Expect filtering to be called (customer groups disabled -> filter to group 0)
        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with('main_table.customer_group_id', 0);

        $manager = $this->createObjectToTest(
            customerGroupCollectionFactory: $factory
        );

        $result = $manager->getCustomerGroups($pricingContext);

        $this->assertSame($collection, $result);
    }

    /**
     * Test that getCustomerGroups caches per store and does not filter on subsequent calls for same store
     */
    public function testGetCustomerGroupsCachesPerStoreAndDoesNotFilterOnSubsequentCalls(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('areCustomerGroupsEnabled')->willReturn(false);
        $pricingContext->method('getStore')->willReturn($store);

        $collection = $this->createMock(CustomerGroupResourceCollection::class);

        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        // Expect filtering to be called only once (not on second call for same store)
        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with('main_table.customer_group_id', 0);

        $manager = $this->createObjectToTest(
            customerGroupCollectionFactory: $factory
        );

        // First call - should filter
        $result1 = $manager->getCustomerGroups($pricingContext);

        // Second call with same store - should return cached collection
        $result2 = $manager->getCustomerGroups($pricingContext);

        $this->assertSame($collection, $result1);
        $this->assertSame($collection, $result2);
    }

    /**
     * Test that filterCustomerGroups filters to group 0 when customer groups are disabled
     */
    public function testFilterCustomerGroupsFiltersToGroup0WhenCustomerGroupsDisabled(): void
    {
        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('areCustomerGroupsEnabled')->willReturn(false);

        $collection = $this->createMock(CustomerGroupResourceCollection::class);

        // Expect filter to group 0 (default group)
        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with('main_table.customer_group_id', 0);

        $manager = $this->createObjectToTest();

        $this->invokeMethod($manager, 'filterCustomerGroups', [$pricingContext, $collection]);
    }

    /**
     * Test that filterCustomerGroups does not filter when customer groups are enabled and no exclusions
     */
    public function testFilterCustomerGroupsDoesNotFilterWhenEnabledAndNoExclusions(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(1);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('areCustomerGroupsEnabled')->willReturn(true);
        $pricingContext->method('getStore')->willReturn($store);

        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $group2 = $this->createStub(Group::class);
        $group2->method('getData')->willReturn(2);

        $collection = $this->createMock(CustomerGroupResourceCollection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$group1, $group2]));

        $repository = $this->createStub(GroupExcludedWebsiteRepositoryInterface::class);
        $repository->method('getCustomerGroupExcludedWebsites')->willReturn([]); // No exclusions

        // Should NOT call addFieldToFilter when there are no exclusions
        $collection->expects($this->never())
            ->method('addFieldToFilter');

        $manager = $this->createObjectToTest(
            groupExcludedWebsiteRepository: $repository
        );

        $this->invokeMethod($manager, 'filterCustomerGroups', [$pricingContext, $collection]);
    }

    /**
     * Test that filterCustomerGroups excludes groups that are excluded for the website
     */
    public function testFilterCustomerGroupsExcludesGroupsForWebsite(): void
    {
        $websiteId = 1;

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn($websiteId);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('areCustomerGroupsEnabled')->willReturn(true);
        $pricingContext->method('getStore')->willReturn($store);

        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $group2 = $this->createStub(Group::class);
        $group2->method('getData')->willReturn(2);

        $group3 = $this->createStub(Group::class);
        $group3->method('getData')->willReturn(3);

        $collection = $this->createMock(CustomerGroupResourceCollection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$group1, $group2, $group3]));

        // Group 1 and 3 are excluded from website 1
        $repository = $this->createStub(GroupExcludedWebsiteRepositoryInterface::class);
        $repository->method('getCustomerGroupExcludedWebsites')
            ->willReturnMap([
                [1, [$websiteId]], // Group 1 excluded from website 1
                [2, []],           // Group 2 not excluded
                [3, [$websiteId]], // Group 3 excluded from website 1
            ]);

        // Expect filter to exclude groups 1 and 3
        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with('main_table.customer_group_id', ['nin' => [1, 3]]);

        // Expect collection to be cleared after filtering
        $collection->expects($this->once())
            ->method('clear');

        $manager = $this->createObjectToTest(
            groupExcludedWebsiteRepository: $repository
        );

        $this->invokeMethod($manager, 'filterCustomerGroups', [$pricingContext, $collection]);
    }

    /**
     * Test that getCustomerGroupCollection creates new collection
     */
    public function testGetCustomerGroupCollectionCreatesNewCollection(): void
    {
        $expectedCollection = $this->createStub(CustomerGroupResourceCollection::class);

        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->willReturn($expectedCollection);

        $manager = $this->createObjectToTest(
            customerGroupCollectionFactory: $factory
        );

        $result = $manager->getCustomerGroupCollection();

        $this->assertSame($expectedCollection, $result);
    }

    /**
     * Test that getCustomerGroupExcludedWebsites delegates to repository
     */
    public function testGetCustomerGroupExcludedWebsitesDelegatesToRepository(): void
    {
        $groupId = 5;
        $expectedWebsites = [1, 3, 5];

        $repository = $this->createMock(GroupExcludedWebsiteRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getCustomerGroupExcludedWebsites')
            ->with($groupId)
            ->willReturn($expectedWebsites);

        $manager = $this->createObjectToTest(
            groupExcludedWebsiteRepository: $repository
        );

        $result = $manager->getCustomerGroupExcludedWebsites($groupId);

        $this->assertSame($expectedWebsites, $result);
    }

    /**
     * Test that different stores get separate cached collections
     */
    public function testDifferentStoresGetSeparateCachedCollections(): void
    {
        $store1 = $this->createStub(Store::class);
        $store1->method('getId')->willReturn(1);

        $store2 = $this->createStub(Store::class);
        $store2->method('getId')->willReturn(2);

        $pricingContext1 = $this->createStub(PricingContextInterface::class);
        $pricingContext1->method('areCustomerGroupsEnabled')->willReturn(false);
        $pricingContext1->method('getStore')->willReturn($store1);

        $pricingContext2 = $this->createStub(PricingContextInterface::class);
        $pricingContext2->method('areCustomerGroupsEnabled')->willReturn(false);
        $pricingContext2->method('getStore')->willReturn($store2);

        $collection1 = $this->createMock(CustomerGroupResourceCollection::class);
        $collection2 = $this->createMock(CustomerGroupResourceCollection::class);

        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')
            ->willReturnOnConsecutiveCalls($collection1, $collection2);

        // Each collection should be filtered once
        $collection1->expects($this->once())
            ->method('addFieldToFilter')
            ->with('main_table.customer_group_id', 0);

        $collection2->expects($this->once())
            ->method('addFieldToFilter')
            ->with('main_table.customer_group_id', 0);

        $manager = $this->createObjectToTest(
            customerGroupCollectionFactory: $factory
        );

        // First store - should create and filter collection1
        $result1 = $manager->getCustomerGroups($pricingContext1);
        $this->assertSame($collection1, $result1);

        // Second store - should create and filter collection2
        $result2 = $manager->getCustomerGroups($pricingContext2);
        $this->assertSame($collection2, $result2);

        // First store again - should return cached collection1
        $result3 = $manager->getCustomerGroups($pricingContext1);
        $this->assertSame($collection1, $result3);
    }
}
