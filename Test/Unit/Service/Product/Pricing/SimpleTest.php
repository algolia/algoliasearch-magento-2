<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\Product\CatalogRulesManager;
use Algolia\AlgoliaSearch\Service\Product\PriceDataFormatter;
use Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups\CustomerGroupsManager;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Simple;
use Algolia\AlgoliaSearch\Service\Product\PricingStrategyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Store\Model\Store;

/**
 * @covers \Algolia\AlgoliaSearch\Service\Product\Pricing\Simple
 */
class SimpleTest extends TestCase
{
    /**
     * Create the object under test with optional dependency overrides
     */
    protected function createObjectToTest(
        ?ConfigHelper $configHelper = null,
        ?PricingHelper $pricingHelper = null,
        ?DiagnosticsLogger $logger = null,
        ?PriceDataFormatter $priceDataFormatter = null,
        ?CatalogRulesManager $catalogRulesManager = null,
        ?CustomerGroupsManager $customerGroupsManager = null,
        ?PricingStrategyResolver $pricingStrategyResolver = null
    ): Simple {
        return new Simple(
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $pricingHelper ?? $this->createStub(PricingHelper::class),
            $logger ?? $this->createStub(DiagnosticsLogger::class),
            $priceDataFormatter ?? $this->createStub(PriceDataFormatter::class),
            $catalogRulesManager ?? $this->createStub(CatalogRulesManager::class),
            $customerGroupsManager ?? $this->createStub(CustomerGroupsManager::class),
            $pricingStrategyResolver ?? $this->createStub(PricingStrategyResolver::class)
        );
    }

    /**
     * Test that Simple can be instantiated with all dependencies
     *
     * Note: Simple extends AbstractProduct with no custom logic.
     * Per testing guidelines, we don't test parent class methods directly.
     * This test verifies proper construction and type.
     */
    public function testCanBeInstantiatedWithDependencies(): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $pricingHelper = $this->createStub(PricingHelper::class);
        $logger = $this->createStub(DiagnosticsLogger::class);
        $priceDataFormatter = $this->createStub(PriceDataFormatter::class);
        $catalogRulesManager = $this->createStub(CatalogRulesManager::class);
        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $pricingStrategyResolver = $this->createStub(PricingStrategyResolver::class);

        $simple = new Simple(
            $configHelper,
            $pricingHelper,
            $logger,
            $priceDataFormatter,
            $catalogRulesManager,
            $customerGroupsManager,
            $pricingStrategyResolver
        );

        $this->assertInstanceOf(Simple::class, $simple);
    }

    /**
     * Test addDefaultPrice (AbstractProduct) sets base price with tax
     */
    public function testAddDefaultPriceSetsBasePriceWithTax(): void
    {
        $basePrice = 100.00;
        $convertedPrice = 100.00;
        $priceWithTax = 120.00;
        $roundedPrice = 120.00;

        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);

        $product = $this->createStub(Product::class);
        $product->method('getPrice')->willReturn($basePrice);
        $product->method('getStoreId')->willReturn(1);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isFptEnabled')->willReturn(false);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('handleConversion')->willReturn($convertedPrice);
        $pricingHelper->method('getTaxPrice')->willReturn($priceWithTax);
        $pricingHelper->method('round')->willReturn($roundedPrice);

        $priceData = $this->createMock(PriceDataInterface::class);
        $priceData->expects($this->once())
            ->method('setPrice')
            ->with($roundedPrice);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getProduct')->willReturn($product);
        $pricingContext->method('shouldIncludeTax')->willReturn(true);

        $simple = $this->createObjectToTest(
            configHelper: $configHelper,
            pricingHelper: $pricingHelper
        );

        $result = $this->invokeMethod($simple, 'addDefaultPrice', [$priceData, $pricingContext]);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test addDefaultPrice includes FPT when enabled
     */
    public function testAddDefaultPriceIncludesFptWhenEnabled(): void
    {
        $basePrice = 100.00;
        $fptAmount = 5.00;

        $product = $this->createStub(Product::class);
        $product->method('getPrice')->willReturn($basePrice);
        $product->method('getStoreId')->willReturn(1);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isFptEnabled')->willReturn(true);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('getWeeeAmount')->willReturn($fptAmount);
        $pricingHelper->method('handleConversion')->willReturnArgument(0);
        $pricingHelper->method('getTaxPrice')->willReturnArgument(1);
        $pricingHelper->method('round')->willReturnArgument(0);

        $priceData = $this->createStub(PriceDataInterface::class);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getProduct')->willReturn($product);
        $pricingContext->method('shouldIncludeTax')->willReturn(false);

        $simple = $this->createObjectToTest(
            configHelper: $configHelper,
            pricingHelper: $pricingHelper
        );

        $this->invokeMethod($simple, 'addDefaultPrice', [$priceData, $pricingContext]);
    }

    /**
     * Test addSpecialPriceDates sets special price dates from product
     */
    public function testAddSpecialPriceDatesSetsDatesFromProduct(): void
    {
        $fromDate = '2024-01-01 00:00:00';
        $toDate = '2024-12-31 23:59:59';
        $fromTimestamp = strtotime($fromDate);
        $toTimestamp = strtotime($toDate);

        $product = $this->createStub(Product::class);
        $product->method('getSpecialFromDate')->willReturn($fromDate);
        $product->method('getSpecialToDate')->willReturn($toDate);

        $priceData = $this->createMock(PriceDataInterface::class);
        $priceData->expects($this->once())
            ->method('setSpecialFromDate')
            ->with($fromTimestamp);
        $priceData->expects($this->once())
            ->method('setSpecialToDate')
            ->with($toTimestamp);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getProduct')->willReturn($product);

        $simple = $this->createObjectToTest();

        $result = $this->invokeMethod($simple, 'addSpecialPriceDates', [$priceData, $pricingContext]);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test addSpecialPriceDates sets empty string when dates are null
     */
    public function testAddSpecialPriceDatesHandlesNullDates(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getSpecialFromDate')->willReturn(null);
        $product->method('getSpecialToDate')->willReturn(null);

        $priceData = $this->createMock(PriceDataInterface::class);
        $priceData->expects($this->once())
            ->method('setSpecialFromDate')
            ->with('');
        $priceData->expects($this->once())
            ->method('setSpecialToDate')
            ->with('');

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getProduct')->willReturn($product);

        $simple = $this->createObjectToTest();

        $result = $this->invokeMethod($simple, 'addSpecialPriceDates', [$priceData, $pricingContext]);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test getRulePrice returns price for specific group from catalog rules
     */
    public function testGetRulePriceReturnsGroupPrice(): void
    {
        $groupId = 2;
        $expectedPrice = 85.50;
        $catalogRules = [
            1 => 90.00,
            2 => $expectedPrice,
            3 => 80.00,
        ];

        $pricingContext = $this->createStub(PricingContextInterface::class);

        $simple = $this->createObjectToTest();

        $result = $this->invokeMethod($simple, 'getRulePrice', [$pricingContext, $catalogRules, $groupId]);

        $this->assertSame($expectedPrice, $result);
    }

    /**
     * Test getTierPrice returns empty array when product has no tier prices
     */
    public function testGetTierPriceReturnsEmptyArrayWhenNoTierPrices(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getTierPrices')->willReturn(null);

        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getProduct')->willReturn($product);

        $simple = $this->createObjectToTest(customerGroupsManager: $customerGroupsManager);

        $result = $this->invokeMethod($simple, 'getTierPrice', [$pricingContext]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getTierPrice selects minimum tier price for each group
     */
    public function testGetTierPriceSelectsMinimumForEachGroup(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(1);

        $tierPrice1 = $this->createStub(\Magento\Catalog\Api\Data\ProductTierPriceInterface::class);
        $tierPrice1->method('getCustomerGroupId')->willReturn(1);
        $tierPrice1->method('getValue')->willReturn(80.00);

        $tierPrice2 = $this->createStub(\Magento\Catalog\Api\Data\ProductTierPriceInterface::class);
        $tierPrice2->method('getCustomerGroupId')->willReturn(1);
        $tierPrice2->method('getValue')->willReturn(75.00); // Lower price should be selected

        $product = $this->createStub(Product::class);
        $product->method('getTierPrices')->willReturn([$tierPrice1, $tierPrice2]);
        $product->method('getStore')->willReturn($store);

        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('handleConversion')->willReturnArgument(0);
        $pricingHelper->method('getTaxPrice')->willReturnArgument(1);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getProduct')->willReturn($product);
        $pricingContext->method('shouldIncludeTax')->willReturn(false);

        $simple = $this->createObjectToTest(
            pricingHelper: $pricingHelper,
            customerGroupsManager: $customerGroupsManager
        );

        $result = $this->invokeMethod($simple, 'getTierPrice', [$pricingContext]);

        $this->assertArrayHasKey(1, $result);
        $this->assertSame(75.00, $result[1]);
    }

    /**
     * Test getTierPrice handles CUST_GROUP_ALL tier prices
     */
    public function testGetTierPriceHandlesAllCustomerGroupsTierPrice(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(1);

        $tierPriceAll = $this->createStub(\Magento\Catalog\Api\Data\ProductTierPriceInterface::class);
        $tierPriceAll->method('getCustomerGroupId')->willReturn(GroupInterface::CUST_GROUP_ALL);
        $tierPriceAll->method('getValue')->willReturn(70.00);

        $product = $this->createStub(Product::class);
        $product->method('getTierPrices')->willReturn([$tierPriceAll]);
        $product->method('getStore')->willReturn($store);

        $group1 = $this->createStub(Group::class);
        $group1->method('getData')->willReturn(1);

        $groupCollection = $this->createStub(CustomerGroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('handleConversion')->willReturnArgument(0);
        $pricingHelper->method('getTaxPrice')->willReturnArgument(1);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getProduct')->willReturn($product);
        $pricingContext->method('shouldIncludeTax')->willReturn(false);

        $simple = $this->createObjectToTest(
            pricingHelper: $pricingHelper,
            customerGroupsManager: $customerGroupsManager
        );

        $result = $this->invokeMethod($simple, 'getTierPrice', [$pricingContext]);

        $this->assertArrayHasKey(1, $result);
        $this->assertSame(70.00, $result[1]);
    }
}
