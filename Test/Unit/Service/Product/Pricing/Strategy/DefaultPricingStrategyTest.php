<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product\Pricing\Strategy;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterface;
use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\DefaultPricingStrategy;
use Algolia\AlgoliaSearch\Test\TestCase;

/**
 * @covers \Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\DefaultPricingStrategy
 */
class DefaultPricingStrategyTest extends TestCase
{
    /**
     * Create the object under test with optional dependency overrides
     */
    protected function createObjectToTest(
        ?PricingHelper $pricingHelper = null
    ): DefaultPricingStrategy {
        return new DefaultPricingStrategy(
            $pricingHelper ?? $this->createStub(PricingHelper::class)
        );
    }

    /**
     * Test that applyCustomerGroupPrices returns price data unchanged
     */
    public function testApplyCustomerGroupPricesReturnsUnchanged(): void
    {
        $priceData = $this->createStub(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $strategy = $this->createObjectToTest();

        $result = $strategy->applyCustomerGroupPrices($priceData, $pricingContext);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applySpecialPrices returns unchanged when no special price exists
     */
    public function testApplySpecialPricesReturnsUnchangedWhenNoSpecialPrice(): void
    {
        $priceData = $this->createStub(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);
        $specialPricesByGroup = [0 => null];

        $strategy = $this->createObjectToTest();

        $result = $strategy->applySpecialPrices($priceData, $pricingContext, $specialPricesByGroup);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applySpecialPrices returns unchanged when special price is higher than current
     */
    public function testApplySpecialPricesReturnsUnchangedWhenSpecialPriceIsHigher(): void
    {
        $priceData = $this->createMock(PriceDataInterface::class);
        $priceData->method('getPrice')->willReturn(50.00);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('round')->willReturn(60.00);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $specialPricesByGroup = [0 => 60.00];

        // Should NOT call setPrice or setOriginalPrice
        $priceData->expects($this->never())->method('setPrice');
        $priceData->expects($this->never())->method('setOriginalPrice');

        $strategy = $this->createObjectToTest(pricingHelper: $pricingHelper);

        $result = $strategy->applySpecialPrices($priceData, $pricingContext, $specialPricesByGroup);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applySpecialPrices applies lower special price and sets original
     */
    public function testApplySpecialPricesAppliesLowerPriceAndSetsOriginal(): void
    {
        $currentPrice = 100.00;
        $specialPrice = 80.00;

        $priceData = $this->createMock(PriceDataInterface::class);
        $priceData->method('getPrice')->willReturn($currentPrice);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('round')->willReturn($specialPrice);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $specialPricesByGroup = [0 => $specialPrice];

        // Expect original price to be set to current price
        $priceData->expects($this->once())
            ->method('setOriginalPrice')
            ->with($currentPrice);

        // Expect price to be updated to special price
        $priceData->expects($this->once())
            ->method('setPrice')
            ->with($specialPrice);

        $strategy = $this->createObjectToTest(pricingHelper: $pricingHelper);

        $result = $strategy->applySpecialPrices($priceData, $pricingContext, $specialPricesByGroup);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applyTierPrices returns unchanged when no tier price exists
     */
    public function testApplyTierPricesReturnsUnchangedWhenNoTierPrice(): void
    {
        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);
        $tierPricesByGroup = [];

        // Should NOT call setTierPrice
        $priceData->expects($this->never())->method('setTierPrice');

        $strategy = $this->createObjectToTest();

        $result = $strategy->applyTierPrices($priceData, $pricingContext, $tierPricesByGroup);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applyTierPrices applies tier price when it exists
     */
    public function testApplyTierPricesAppliesTierPriceWhenExists(): void
    {
        $tierPrice = 75.00;

        $priceData = $this->createMock(PriceDataInterface::class);

        $pricingHelper = $this->createStub(PricingHelper::class);
        $pricingHelper->method('round')->willReturn($tierPrice);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $tierPricesByGroup = [0 => $tierPrice];

        // Expect tier price to be set
        $priceData->expects($this->once())
            ->method('setTierPrice')
            ->with($tierPrice);

        $strategy = $this->createObjectToTest(pricingHelper: $pricingHelper);

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
     * Test that applyPriceRange sets min and max when they differ
     */
    public function testApplyPriceRangeSetsMinAndMaxWhenTheyDiffer(): void
    {
        $min = 30.00;
        $max = 90.00;

        $priceData = $this->createMock(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);
        $minMaxPrices->method('getMin')->willReturn($min);
        $minMaxPrices->method('getMax')->willReturn($max);

        // Expect min and max to be set
        $priceData->expects($this->once())
            ->method('setMinPrice')
            ->with($min);

        $priceData->expects($this->once())
            ->method('setMaxPrice')
            ->with($max);

        $strategy = $this->createObjectToTest();

        $result = $strategy->applyPriceRange($priceData, $pricingContext, $minMaxPrices);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applyChildrenCustomerGroupPrices returns price data unchanged
     */
    public function testApplyChildrenCustomerGroupPricesReturnsUnchanged(): void
    {
        $priceData = $this->createStub(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);
        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);

        $strategy = $this->createObjectToTest();

        $result = $strategy->applyChildrenCustomerGroupPrices($priceData, $pricingContext, $minMaxPrices);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test that applyCustomerGroupsFinalPrices returns price data unchanged
     */
    public function testApplyCustomerGroupsFinalPricesReturnsUnchanged(): void
    {
        $priceData = $this->createStub(PriceDataInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);
        $min = 25.00;

        $strategy = $this->createObjectToTest();

        $result = $strategy->applyCustomerGroupsFinalPrices($priceData, $pricingContext, $min);

        $this->assertSame($priceData, $result);
    }
}
