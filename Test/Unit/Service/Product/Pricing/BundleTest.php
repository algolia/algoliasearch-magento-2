<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterface;
use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterfaceFactory;
use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Api\Product\PricingStrategyInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\Product\CatalogRulesManager;
use Algolia\AlgoliaSearch\Service\Product\PriceDataFormatter;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Bundle;
use Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups\CustomerGroupsManager;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\CustomerGroupPricingStrategy;
use Algolia\AlgoliaSearch\Service\Product\PricingStrategyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Product;

/**
 * @covers \Algolia\AlgoliaSearch\Service\Product\Pricing\Bundle
 */
class BundleTest extends TestCase
{
    /**
     * Create the object under test with optional dependency overrides
     */
    protected function createObjectToTest(
        ?MinMaxPricesInterfaceFactory $minMaxPricesFactory = null,
        ?ConfigHelper $configHelper = null,
        ?PricingHelper $pricingHelper = null,
        ?DiagnosticsLogger $logger = null,
        ?PriceDataFormatter $priceDataFormatter = null,
        ?CatalogRulesManager $catalogRulesManager = null,
        ?CustomerGroupsManager $customerGroupsManager = null,
        ?PricingStrategyResolver $pricingStrategyResolver = null
    ): Bundle {
        return new Bundle(
            $minMaxPricesFactory ?? $this->createStub(MinMaxPricesInterfaceFactory::class),
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
     * Test that getChildrenMinMaxPrices returns zeros when no subProducts
     */
    public function testGetChildrenMinMaxPricesReturnsZerosWhenNoSubProducts(): void
    {
        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);

        $minMaxPricesFactory = $this->createMock(MinMaxPricesInterfaceFactory::class);
        $minMaxPricesFactory->expects($this->once())
            ->method('create')
            ->with([
                'min' => 0.00,
                'max' => 0.00,
                'minOriginal' => 0.00,
                'maxOriginal' => 0.00,
            ])
            ->willReturn($minMaxPrices);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getSubProducts')->willReturn([]);

        $bundle = $this->createObjectToTest(minMaxPricesFactory: $minMaxPricesFactory);

        $result = $this->invokeMethod($bundle, 'getChildrenMinMaxPrices', [$pricingContext]);

        $this->assertSame($minMaxPrices, $result);
    }

    /**
     * Test that getChildrenMinMaxPrices creates MinMaxPrices with zeros when no subProducts
     *
     * Note: Testing with subProducts requires extensive mocking of protected methods,
     * so we focus on the empty subProducts case which exercises the Bundle override logic.
     */
    public function testGetChildrenMinMaxPricesWithEmptySubProductsVerifiesBundleLogic(): void
    {
        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);

        $minMaxPricesFactory = $this->createMock(MinMaxPricesInterfaceFactory::class);
        $minMaxPricesFactory->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                // Bundle override should still create with zeros when no subProducts
                return $data['min'] === 0.00
                    && $data['max'] === 0.00
                    && $data['minOriginal'] === 0.00
                    && $data['maxOriginal'] === 0.00;
            }))
            ->willReturn($minMaxPrices);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getSubProducts')->willReturn([]);

        $bundle = $this->createObjectToTest(minMaxPricesFactory: $minMaxPricesFactory);

        $result = $this->invokeMethod($bundle, 'getChildrenMinMaxPrices', [$pricingContext]);

        $this->assertSame($minMaxPrices, $result);
    }

    /**
     * Test that addCustomerGroupsFinalPrices passes bundle flag to strategy
     */
    public function testAddCustomerGroupsFinalPricesPassesBundleFlagToStrategy(): void
    {
        $min = 50.00;

        $priceData = $this->createStub(PriceDataInterface::class);

        $pricingContext = $this->createStub(PricingContextInterface::class);

        $customerGroupPricingStrategy = $this->createMock(CustomerGroupPricingStrategy::class);
        $customerGroupPricingStrategy->expects($this->once())
            ->method('applyCustomerGroupsFinalPrices')
            ->with($priceData, $pricingContext, $min, true) // Bundle flag should be true
            ->willReturn($priceData);

        $bundle = $this->createObjectToTest();

        // Set the strategy (normally done by calculatePriceData via PricingStrategyResolver)
        $this->setPrivateProperty($bundle, 'pricingStrategy', $customerGroupPricingStrategy);

        $result = $this->invokeMethod($bundle, 'addCustomerGroupsFinalPrices', [$priceData, $pricingContext, $min]);

        $this->assertSame($priceData, $result);
    }
}
