<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Service\Product\PriceDataFormatter;
use Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups\CustomerGroupsManager;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Framework\Currency;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \Algolia\AlgoliaSearch\Service\Product\PriceDataFormatter
 */
class PriceDataFormatterTest extends TestCase
{
    /**
     * Create the object under test with optional dependency overrides
     */
    protected function createObjectToTest(
        ?PricingHelper $pricingHelper = null,
        ?ConfigHelper $configHelper = null,
        ?CustomerGroupsManager $customerGroupsManager = null,
        ?PriceCurrencyInterface $priceCurrency = null
    ): PriceDataFormatter {
        return new PriceDataFormatter(
            $pricingHelper ?? $this->createStub(PricingHelper::class),
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $customerGroupsManager ?? $this->createStub(CustomerGroupsManager::class),
            $priceCurrency ?? $this->createStub(PriceCurrencyInterface::class)
        );
    }

    /**
     * Helper to create a mock PriceCurrencyInterface with a currency that formats prices
     *
     * Note: Currency::formatPrecision is final, so we can't mock it directly.
     * Instead, we create a simple mock that returns formatted strings.
     */
    protected function createPriceCurrencyMock(): PriceCurrencyInterface
    {
        // Currency::formatPrecision is final and cannot be mocked in PHPUnit
        // Create an anonymous class that mimics Currency's formatPrecision behavior
        $currency = new class {
            public function formatPrecision($price, $precision, $options = [], $includeContainer = true)
            {
                return '$' . number_format($price, 2);
            }
        };

        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('getCurrency')->willReturn($currency);

        return $priceCurrency;
    }

    /**
     * Test that formatPrice calls PriceCurrency correctly
     */
    public function testFormatPriceCallsPriceCurrency(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);

        // Use anonymous class since Currency::formatPrecision is final
        $currency = new class {
            public function formatPrecision($price, $precision, $options = [], $includeContainer = true)
            {
                return '$' . number_format($price, 2);
            }
        };

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getStoreLocale')->willReturn('en_US');

        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->expects($this->once())
            ->method('getCurrency')
            ->with($store, 'USD')
            ->willReturn($currency);

        $formatter = $this->createObjectToTest(
            configHelper: $configHelper,
            priceCurrency: $priceCurrency
        );

        // Call formatPrice - it should call getCurrency
        $formatter->formatPrice(99.99, $store, 'USD');
    }

    /**
     * Test that formatPriceDataObject formats only default group when customer groups are disabled
     */
    public function testFormatPriceDataObjectWithCustomerGroupsDisabled(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);

        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getCurrencyCode')->willReturn('USD');

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isCustomerGroupsEnabled')->willReturn(false);
        $configHelper->method('getStoreLocale')->willReturn('en_US');

        $priceCurrency = $this->createPriceCurrencyMock();

        // Setup price data expectations
        $priceData->method('hasPrice')->with(null)->willReturn(true);
        $priceData->method('getPrice')->with(null)->willReturn(99.99);

        // Assert formatted price is set (format is handled by Magento's PriceCurrency)
        $priceData->expects($this->once())
            ->method('setFormattedPrice')
            ->with($this->anything(), null);

        // Assert cleanup is called
        $priceData->expects($this->once())
            ->method('unsetMinPrice')
            ->with(null);

        $priceData->expects($this->once())
            ->method('unsetMaxPrice')
            ->with(null);

        $formatter = $this->createObjectToTest(
            configHelper: $configHelper,
            priceCurrency: $priceCurrency
        );

        $result = $formatter->formatPriceDataObject($priceData, $pricingContext);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that formatPriceDataObject formats all customer groups when enabled
     */
    public function testFormatPriceDataObjectWithCustomerGroupsEnabled(): void
    {
        $store = $this->createStub(Store::class);
        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getCurrencyCode')->willReturn('USD');

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isCustomerGroupsEnabled')->willReturn(true);

        // Create customer group stubs
        $group1 = $this->createStub(Group::class);
        $group1->method('getId')->willReturn(1);

        $group2 = $this->createStub(Group::class);
        $group2->method('getId')->willReturn(2);

        // Create a mock collection that is iterable
        $groupCollection = $this->createStub(GroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1, $group2]));

        $customerGroupsManager = $this->createStub(CustomerGroupsManager::class);
        $customerGroupsManager->method('getCustomerGroups')->willReturn($groupCollection);

        // Expect formatFields to be called for default + 2 groups = 3 times
        $priceData->method('hasPrice')->willReturn(false);

        $priceData->expects($this->exactly(3))
            ->method('unsetMinPrice');

        $priceData->expects($this->exactly(3))
            ->method('unsetMaxPrice');

        $formatter = $this->createObjectToTest(
            configHelper: $configHelper,
            customerGroupsManager: $customerGroupsManager
        );

        $result = $formatter->formatPriceDataObject($priceData, $pricingContext);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test formatFields sets formatted price when price exists
     */
    public function testFormatFieldsSetsFormattedPriceWhenPriceExists(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);

        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getCurrencyCode')->willReturn('EUR');

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getStoreLocale')->willReturn('en_US');

        $priceCurrency = $this->createPriceCurrencyMock();

        $priceData->method('hasPrice')->with(0)->willReturn(true);
        $priceData->method('getPrice')->with(0)->willReturn(149.99);

        $priceData->expects($this->once())
            ->method('setFormattedPrice')
            ->with($this->stringContains('149.99'), 0);

        $priceData->method('unsetMinPrice');
        $priceData->method('unsetMaxPrice');

        $formatter = $this->createObjectToTest(
            configHelper: $configHelper,
            priceCurrency: $priceCurrency
        );

        $result = $this->invokeMethod(
            $formatter,
            'formatFields',
            [$priceData, $pricingContext, 0]
        );

        $this->assertSame($priceData, $result);
    }

    /**
     * Test formatFields does not set formatted price when price does not exist
     */
    public function testFormatFieldsDoesNotSetFormattedPriceWhenPriceDoesNotExist(): void
    {
        $store = $this->createStub(Store::class);
        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getCurrencyCode')->willReturn('USD');

        $priceData->method('hasPrice')->with(null)->willReturn(false);

        $priceData->expects($this->never())
            ->method('setFormattedPrice');

        $priceData->method('unsetMinPrice');
        $priceData->method('unsetMaxPrice');

        $formatter = $this->createObjectToTest();

        $this->invokeMethod(
            $formatter,
            'formatFields',
            [$priceData, $pricingContext, null]
        );
    }

    /**
     * Test formatFields sets price range when min and max prices differ
     */
    public function testFormatFieldsSetsPriceRangeWhenMinMaxDiffer(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);

        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getCurrencyCode')->willReturn('USD');

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getStoreLocale')->willReturn('en_US');

        $priceCurrency = $this->createPriceCurrencyMock();

        $priceData->method('hasPrice')->willReturn(false);
        $priceData->method('hasMinPrice')->with(null)->willReturn(true);
        $priceData->method('hasMaxPrice')->with(null)->willReturn(true);
        $priceData->method('getMinPrice')->with(null)->willReturn(50.00);
        $priceData->method('getMaxPrice')->with(null)->willReturn(150.00);

        $priceData->expects($this->once())
            ->method('setFormattedPrice')
            ->with($this->stringContains(' - '), null);

        $priceData->method('unsetMinPrice');
        $priceData->method('unsetMaxPrice');

        $formatter = $this->createObjectToTest(
            configHelper: $configHelper,
            priceCurrency: $priceCurrency
        );

        $this->invokeMethod(
            $formatter,
            'formatFields',
            [$priceData, $pricingContext, null]
        );
    }

    /**
     * Test formatFields does not set price range when min equals max
     */
    public function testFormatFieldsDoesNotSetPriceRangeWhenMinEqualsMax(): void
    {
        $store = $this->createStub(Store::class);
        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getCurrencyCode')->willReturn('USD');

        $priceData->method('hasPrice')->willReturn(false);
        $priceData->method('hasMinPrice')->willReturn(true);
        $priceData->method('hasMaxPrice')->willReturn(true);
        $priceData->method('getMinPrice')->willReturn(100.00);
        $priceData->method('getMaxPrice')->willReturn(100.00);

        // Should not set formatted price for range since min == max
        $priceData->expects($this->never())
            ->method('setFormattedPrice');

        $priceData->method('unsetMinPrice');
        $priceData->method('unsetMaxPrice');

        $formatter = $this->createObjectToTest();

        $this->invokeMethod(
            $formatter,
            'formatFields',
            [$priceData, $pricingContext, null]
        );
    }

    /**
     * Test formatFields sets formatted original price when original price is higher than current price
     */
    public function testFormatFieldsSetsFormattedOriginalPriceWhenHigherThanCurrentPrice(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);

        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getCurrencyCode')->willReturn('USD');

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getStoreLocale')->willReturn('en_US');

        $priceCurrency = $this->createPriceCurrencyMock();

        $priceData->method('hasPrice')->willReturn(false);
        $priceData->method('hasOriginalPrice')->with(null)->willReturn(true);
        $priceData->method('getOriginalPrice')->with(null)->willReturn(199.99);
        $priceData->method('getPrice')->with(null)->willReturn(149.99);
        $priceData->method('hasMinOriginalPrice')->willReturn(false);

        $priceData->expects($this->once())
            ->method('setFormattedOriginalPrice')
            ->with($this->anything(), null);

        $priceData->method('unsetMinPrice');
        $priceData->method('unsetMaxPrice');

        $formatter = $this->createObjectToTest(
            configHelper: $configHelper,
            priceCurrency: $priceCurrency
        );

        $this->invokeMethod(
            $formatter,
            'formatFields',
            [$priceData, $pricingContext, null]
        );
    }

    /**
     * Test formatFields does not set formatted original price when not higher than current price
     */
    public function testFormatFieldsDoesNotSetFormattedOriginalPriceWhenNotHigherThanCurrentPrice(): void
    {
        $store = $this->createStub(Store::class);
        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getCurrencyCode')->willReturn('USD');

        $priceData->method('hasPrice')->willReturn(false);
        $priceData->method('hasOriginalPrice')->willReturn(true);
        $priceData->method('getOriginalPrice')->willReturn(100.00);
        $priceData->method('getPrice')->willReturn(150.00); // Current price is higher

        $priceData->expects($this->never())
            ->method('setFormattedOriginalPrice');

        $priceData->method('unsetMinPrice');
        $priceData->method('unsetMaxPrice');

        $formatter = $this->createObjectToTest();

        $this->invokeMethod(
            $formatter,
            'formatFields',
            [$priceData, $pricingContext, null]
        );
    }

    /**
     * Test formatFields sets original price range when min original price conditions are met
     */
    public function testFormatFieldsSetsOriginalPriceRangeWhenConditionsMet(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);

        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getCurrencyCode')->willReturn('USD');

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getStoreLocale')->willReturn('en_US');

        $priceCurrency = $this->createPriceCurrencyMock();

        $priceData->method('hasPrice')->willReturn(false);
        $priceData->method('hasOriginalPrice')->willReturn(true);
        $priceData->method('getOriginalPrice')->willReturn(200.00);
        $priceData->method('getPrice')->willReturn(150.00);
        $priceData->method('hasMinOriginalPrice')->with(null)->willReturn(true);
        $priceData->method('getMinOriginalPrice')->with(null)->willReturn(180.00);
        $priceData->method('getMinPrice')->with(null)->willReturn(120.00);

        // Should set range format: first max original, then minOriginal - maxOriginal range
        $priceData->expects($this->exactly(2))
            ->method('setFormattedOriginalPrice')
            ->willReturnCallback(function ($price, $groupId) {
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    // First call: just the max original price
                    $this->assertIsString($price);
                    $this->assertNull($groupId);
                } elseif ($callCount === 2) {
                    // Second call: the range (contains ' - ')
                    $this->assertIsString($price);
                    $this->assertStringContainsString(' - ', $price);
                    $this->assertNull($groupId);
                }

                return true;
            });

        $priceData->method('unsetMinPrice');
        $priceData->method('unsetMaxPrice');

        $formatter = $this->createObjectToTest(
            configHelper: $configHelper,
            priceCurrency: $priceCurrency
        );

        $this->invokeMethod(
            $formatter,
            'formatFields',
            [$priceData, $pricingContext, null]
        );
    }

    /**
     * Test formatFields sets formatted tier price when tier price exists
     */
    public function testFormatFieldsSetsFormattedTierPriceWhenTierPriceExists(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);

        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getCurrencyCode')->willReturn('GBP');

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getStoreLocale')->willReturn('en_GB');

        $priceCurrency = $this->createPriceCurrencyMock();

        $priceData->method('hasPrice')->willReturn(false);
        $priceData->method('hasTierPrice')->with(1)->willReturn(true);
        $priceData->method('getTierPrice')->with(1)->willReturn(89.99);

        $priceData->expects($this->once())
            ->method('setFormattedTierPrice')
            ->with($this->anything(), 1);

        $priceData->method('unsetMinPrice');
        $priceData->method('unsetMaxPrice');

        $formatter = $this->createObjectToTest(
            configHelper: $configHelper,
            priceCurrency: $priceCurrency
        );

        $this->invokeMethod(
            $formatter,
            'formatFields',
            [$priceData, $pricingContext, 1]
        );
    }

    /**
     * Test formatFields does not set formatted tier price when tier price does not exist
     */
    public function testFormatFieldsDoesNotSetFormattedTierPriceWhenTierPriceDoesNotExist(): void
    {
        $store = $this->createStub(Store::class);
        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getCurrencyCode')->willReturn('USD');

        $priceData->method('hasPrice')->willReturn(false);
        $priceData->method('hasTierPrice')->with(null)->willReturn(false);

        $priceData->expects($this->never())
            ->method('setFormattedTierPrice');

        $priceData->method('unsetMinPrice');
        $priceData->method('unsetMaxPrice');

        $formatter = $this->createObjectToTest();

        $this->invokeMethod(
            $formatter,
            'formatFields',
            [$priceData, $pricingContext, null]
        );
    }

    /**
     * Test formatFields always cleans up min and max price values
     */
    public function testFormatFieldsAlwaysCleansUpMinMaxPrices(): void
    {
        $store = $this->createStub(Store::class);
        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $pricingContext->method('getStore')->willReturn($store);
        $pricingContext->method('getCurrencyCode')->willReturn('USD');

        $priceData->method('hasPrice')->willReturn(false);

        $groupId = 2;

        $priceData->expects($this->once())
            ->method('unsetMinPrice')
            ->with($groupId);

        $priceData->expects($this->once())
            ->method('unsetMaxPrice')
            ->with($groupId);

        $formatter = $this->createObjectToTest();

        $result = $this->invokeMethod(
            $formatter,
            'formatFields',
            [$priceData, $pricingContext, $groupId]
        );

        $this->assertSame($priceData, $result);
    }
}
