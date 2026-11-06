<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product\Pricing\Strategy;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterface;
use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups\CustomerGroupsManager;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\CustomerGroupPricingStrategy;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\Price as PriceModel;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfo\Base as PriceInfo;
use Magento\Store\Model\Store;

/**
 * @covers \Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\CustomerGroupPricingStrategy
 */
class CustomerGroupPricingStrategyTest extends TestCase
{
    /**
     * Create the object under test with optional dependency overrides
     */
    protected function createObjectToTest(
        ?PricingHelper $pricingHelper = null,
        ?CustomerGroupsManager $customerGroupsManager = null
    ): CustomerGroupPricingStrategy {
        return new CustomerGroupPricingStrategy(
            $pricingHelper ?? $this->createStub(PricingHelper::class),
            $customerGroupsManager ?? $this->createStub(CustomerGroupsManager::class)
        );
    }

    /**
     * Test that applyCustomerGroupPrices sets prices for each customer group
     */
    public function testApplyCustomerGroupPricesSetsPricesForEachGroup(): void
    {
        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $group2 = $this->createStub(Group::class);
        $group2->method('getData')->willReturn(2);

        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1, $group2]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(1);

        $finalPrice = $this->createStub(PriceInterface::class);
        $finalPrice->method('getValue')->willReturn(90.00);

        $priceInfo = $this->createStub(PriceInfo::class);
        $priceInfo->method('getPrice')->willReturn($finalPrice);

        $product = $this->createStub(Product::class);
        $product->method('getStore')->willReturn($store);
        $product->method('getPriceInfo')->willReturn($priceInfo);

        $priceData = $this->createMock(PriceDataInterface::class);
        $priceData->method('getPrice')->willReturnOnConsecutiveCalls(100.00, 100.00, 90.00, 90.00);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('handleConversion')->willReturnArgument(0);
        $pricingHelper->method('getTaxPrice')->willReturnArgument(1);

        // Expect setPrice to be called for each group
        $priceData->expects($this->exactly(2))
            ->method('setPrice');

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getProduct')->willReturn($product);

        $strategy = $this->createObjectToTest(
            pricingHelper: $pricingHelper,
            customerGroupsManager: $customerGroupsManager
        );

        $result = $strategy->applyCustomerGroupPrices($priceData, $pricingContext);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applyCustomerGroupPrices handles when discounted price is false or zero
     */
    public function testApplyCustomerGroupPricesHandlesNullDiscountedPrice(): void
    {
        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(1);

        $finalPrice = $this->createStub(PriceInterface::class);
        $finalPrice->method('getValue')->willReturn(0.0);

        $priceInfo = $this->createStub(PriceInfo::class);
        $priceInfo->method('getPrice')->willReturn($finalPrice);

        $product = $this->createStub(Product::class);
        $product->method('getStore')->willReturn($store);
        $product->method('getPriceInfo')->willReturn($priceInfo);

        $priceData = $this->createMock(PriceDataInterface::class);
        $priceData->method('getPrice')->willReturn(100.00);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('handleConversion')->willReturn(0.0);
        $pricingHelper->method('getTaxPrice')->willReturnArgument(1);

        // Expect setPrice to be called with default price when discounted is 0
        $priceData->expects($this->once())
            ->method('setPrice')
            ->with(100.00, 1);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getProduct')->willReturn($product);

        $strategy = $this->createObjectToTest(
            pricingHelper: $pricingHelper,
            customerGroupsManager: $customerGroupsManager
        );

        $strategy->applyCustomerGroupPrices($priceData, $pricingContext);
    }

    /**
     * Test that applySpecialPrices applies special price when lower for customer groups
     */
    public function testApplySpecialPricesAppliesLowerPriceForCustomerGroups(): void
    {
        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $priceData = $this->createMock(PriceDataInterface::class);
        // Need 4 calls: getPrice($groupId) in condition, getPrice() in nested condition check, getPrice($groupId) after setPrice, getPrice() in final check
        $priceData->method('getPrice')->willReturnOnConsecutiveCalls(100.00, 100.00, 70.00, 70.00);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('round')->willReturn(70.00);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $specialPricesByGroup = [1 => 70.00, 0 => null];

        // Expect setPrice for group 1 (special is lower)
        $priceData->expects($this->once())
            ->method('setPrice')
            ->with(70.00, 1);

        $strategy = $this->createObjectToTest(
            pricingHelper: $pricingHelper,
            customerGroupsManager: $customerGroupsManager
        );

        $result = $strategy->applySpecialPrices($priceData, $pricingContext, $specialPricesByGroup);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applySpecialPrices also applies to default group
     */
    public function testApplySpecialPricesAlsoAppliesToDefaultGroup(): void
    {
        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $priceData = $this->createMock(PriceDataInterface::class);
        $priceData->method('getPrice')->willReturn(100.00);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('round')->willReturn(85.00);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $specialPricesByGroup = [0 => 85.00];

        // Expect setOriginalPrice and setPrice for default group
        $priceData->expects($this->once())
            ->method('setOriginalPrice')
            ->with(100.00);

        $priceData->expects($this->once())
            ->method('setPrice')
            ->with(85.00);

        $strategy = $this->createObjectToTest(
            pricingHelper: $pricingHelper,
            customerGroupsManager: $customerGroupsManager
        );

        $result = $strategy->applySpecialPrices($priceData, $pricingContext, $specialPricesByGroup);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applyTierPrices sets tier prices for customer groups
     */
    public function testApplyTierPricesSetsTierPricesForCustomerGroups(): void
    {
        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $group2 = $this->createStub(Group::class);
        $group2->method('getData')->willReturn(2);

        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1, $group2]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $priceData = $this->createMock(PriceDataInterface::class);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('round')->willReturnArgument(0);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $tierPricesByGroup = [1 => 65.00, 2 => 70.00, 0 => 75.00];

        // Expect setTierPrice for each group + default
        $priceData->expects($this->exactly(3))
            ->method('setTierPrice');

        $strategy = $this->createObjectToTest(
            pricingHelper: $pricingHelper,
            customerGroupsManager: $customerGroupsManager
        );

        $result = $strategy->applyTierPrices($priceData, $pricingContext, $tierPricesByGroup);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applyPriceRange returns unchanged when min equals max
     */
    public function testApplyPriceRangeReturnsUnchangedWhenMinEqualsMax(): void
    {
        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);
        $minMaxPrices->method('getMin')->willReturn(50.00);
        $minMaxPrices->method('getMax')->willReturn(50.00);

        // Should NOT call setMinPrice or setMaxPrice
        $priceData->expects($this->never())->method('setMinPrice');
        $priceData->expects($this->never())->method('setMaxPrice');

        $strategy = $this->createObjectToTest();

        $result = $strategy->applyPriceRange($priceData, $pricingContext, $minMaxPrices);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applyPriceRange sets min and max for all groups when they differ
     */
    public function testApplyPriceRangeSetsMinAndMaxForAllGroups(): void
    {
        $min = 30.00;
        $max = 90.00;

        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);
        $minMaxPrices->method('getMin')->willReturn($min);
        $minMaxPrices->method('getMax')->willReturn($max);

        // Expect setMinPrice and setMaxPrice for default + each group (2 calls each)
        $priceData->expects($this->exactly(2))
            ->method('setMinPrice');

        $priceData->expects($this->exactly(2))
            ->method('setMaxPrice');

        $strategy = $this->createObjectToTest(
            customerGroupsManager: $customerGroupsManager
        );

        $result = $strategy->applyPriceRange($priceData, $pricingContext, $minMaxPrices);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applyChildrenCustomerGroupPrices returns unchanged when max >= maxOriginal
     */
    public function testApplyChildrenCustomerGroupPricesReturnsUnchangedWhenMaxNotLessThanMaxOriginal(): void
    {
        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);
        $minMaxPrices->method('getMax')->willReturn(100.00);
        $minMaxPrices->method('getMaxOriginal')->willReturn(100.00);

        // Should NOT call setOriginalPrice
        $priceData->expects($this->never())->method('setOriginalPrice');

        $strategy = $this->createObjectToTest();

        $result = $strategy->applyChildrenCustomerGroupPrices($priceData, $pricingContext, $minMaxPrices);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applyChildrenCustomerGroupPrices sets original prices when max < maxOriginal
     */
    public function testApplyChildrenCustomerGroupPricesSetsOriginalPricesWhenMaxLessThanMaxOriginal(): void
    {
        $max = 80.00;
        $maxOriginal = 120.00;

        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);
        $minMaxPrices->method('getMax')->willReturn($max);
        $minMaxPrices->method('getMaxOriginal')->willReturn($maxOriginal);

        // Expect setOriginalPrice for each group
        $priceData->expects($this->once())
            ->method('setOriginalPrice')
            ->with($maxOriginal, 1);

        $strategy = $this->createObjectToTest(
            customerGroupsManager: $customerGroupsManager
        );

        $result = $strategy->applyChildrenCustomerGroupPrices($priceData, $pricingContext, $minMaxPrices);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applyCustomerGroupsFinalPrices sets default min when no subProducts and price is zero
     */
    public function testApplyCustomerGroupsFinalPricesSetsDefaultMinWhenNoSubProductsAndZeroPrice(): void
    {
        $min = 25.00;

        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $priceData = $this->createMock(PriceDataInterface::class);
        $priceData->method('getPrice')->willReturn(0.0);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getSubProducts')->willReturn([]);

        // Expect setPrice with min value when current price is zero
        $priceData->expects($this->once())
            ->method('setPrice')
            ->with($min, 1);

        $strategy = $this->createObjectToTest(
            customerGroupsManager: $customerGroupsManager
        );

        $result = $strategy->applyCustomerGroupsFinalPrices($priceData, $pricingContext, $min);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applyCustomerGroupsFinalPrices handles subProducts correctly
     */
    public function testApplyCustomerGroupsFinalPricesHandlesSubProductsCorrectly(): void
    {
        $min = 10.00;

        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(1);

        $priceModel = $this->createStub(PriceModel::class);
        $priceModel->method('getFinalPrice')->willReturn(20.00);

        $subProduct = $this->createStub(Product::class);
        $subProduct->method('getStore')->willReturn($store);
        $subProduct->method('getPriceModel')->willReturn($priceModel);

        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $priceData = $this->createMock(PriceDataInterface::class);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('getTaxPrice')->willReturnArgument(1);
        $pricingHelper->method('handleConversion')->willReturnArgument(0);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getSubProducts')->willReturn([$subProduct]);
        $pricingContext->method('getProduct')->willReturn($this->createStub(Product::class));
        $pricingContext->method('shouldIncludeTax')->willReturn(false);

        // Expect setPrice, setMinPrice, setMaxPrice for groups with subProducts
        $priceData->expects($this->atLeastOnce())
            ->method('setPrice');

        $priceData->expects($this->atLeastOnce())
            ->method('setMinPrice');

        $priceData->expects($this->atLeastOnce())
            ->method('setMaxPrice');

        $strategy = $this->createObjectToTest(
            pricingHelper: $pricingHelper,
            customerGroupsManager: $customerGroupsManager
        );

        $result = $strategy->applyCustomerGroupsFinalPrices(
            $priceData,
            $pricingContext,
            $min,
            false // Use regular calculation (not bundle)
        );

        $this->assertSame($priceData, $result);
    }
}
