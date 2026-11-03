<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterface;
use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterfaceFactory;
use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\Product\CatalogRulesManager;
use Algolia\AlgoliaSearch\Service\Product\PriceDataFormatter;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Configurable;
use Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups\CustomerGroupsManager;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\CustomerGroupPricingStrategy;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\DefaultPricingStrategy;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;

/**
 * @covers \Algolia\AlgoliaSearch\Service\Product\Pricing\Configurable
 */
class ConfigurableTest extends TestCase
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
        ?DefaultPricingStrategy $defaultPricingStrategy = null,
        ?CustomerGroupPricingStrategy $customerGroupPricingStrategy = null
    ): Configurable {
        return new Configurable(
            $minMaxPricesFactory ?? $this->createStub(MinMaxPricesInterfaceFactory::class),
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $pricingHelper ?? $this->createStub(PricingHelper::class),
            $logger ?? $this->createStub(DiagnosticsLogger::class),
            $priceDataFormatter ?? $this->createStub(PriceDataFormatter::class),
            $catalogRulesManager ?? $this->createStub(CatalogRulesManager::class),
            $customerGroupsManager ?? $this->createStub(CustomerGroupsManager::class),
            $defaultPricingStrategy ?? $this->createStub(DefaultPricingStrategy::class),
            $customerGroupPricingStrategy ?? $this->createStub(CustomerGroupPricingStrategy::class)
        );
    }

    /**
     * Test that getRulePrice uses parent implementation when no subProducts
     */
    public function testGetRulePriceUsesParentImplementationWhenNoSubProducts(): void
    {
        $groupId = 1;
        $catalogRules = [1 => 75.00];

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getSubProducts')->willReturn([]);

        $configurable = $this->createObjectToTest();

        $result = $this->invokeMethod($configurable, 'getRulePrice', [$pricingContext, $catalogRules, $groupId]);

        $this->assertSame(75.00, $result);
    }

    /**
     * Test that getRulePrice returns minimum child price when subProducts exist
     */
    public function testGetRulePriceReturnsMinimumChildPriceWhenSubProductsExist(): void
    {
        $groupId = 1;
        $catalogRules = [1 => 100.00];

        $child1 = $this->createStub(Product::class);
        $child2 = $this->createStub(Product::class);

        $typeInstance = $this->createStub(ConfigurableType::class);

        $product = $this->createStub(Product::class);
        $product->method('getTypeInstance')->willReturn($typeInstance);

        $catalogRulesManager = $this->createStub(CatalogRulesManager::class);
        $catalogRulesManager->method('getProductRules')
            ->willReturnOnConsecutiveCalls(
                [1 => 70.00],
                [1 => 80.00]
            );

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getSubProducts')->willReturn([$child1, $child2]);
        $pricingContext->method('getProduct')->willReturn($product);

        $configurable = $this->createObjectToTest(catalogRulesManager: $catalogRulesManager);

        $result = $this->invokeMethod($configurable, 'getRulePrice', [$pricingContext, $catalogRules, $groupId]);

        $this->assertSame(70.00, $result);
    }

    /**
     * Test that getRulePrice uses parent implementation when type instance is not configurable
     */
    public function testGetRulePriceUsesParentImplementationWhenTypeInstanceNotConfigurable(): void
    {
        $groupId = 1;
        $catalogRules = [1 => 90.00];

        $child1 = $this->createStub(Product::class);

        $typeInstance = $this->createStub(\Magento\Catalog\Model\Product\Type\Simple::class);

        $product = $this->createStub(Product::class);
        $product->method('getTypeInstance')->willReturn($typeInstance);
        $product->method('getId')->willReturn(123);

        $logger = $this->createMock(DiagnosticsLogger::class);
        $logger->expects($this->once())
            ->method('debug');

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getSubProducts')->willReturn([$child1]);
        $pricingContext->method('getProduct')->willReturn($product);

        $configurable = $this->createObjectToTest(logger: $logger);

        $result = $this->invokeMethod($configurable, 'getRulePrice', [$pricingContext, $catalogRules, $groupId]);

        $this->assertSame(90.00, $result);
    }

    /**
     * Test getChildrenMinMaxPrices (AbstractProductWithChildren) creates MinMaxPrices object
     */
    public function testGetChildrenMinMaxPricesCreatesMinMaxPricesObject(): void
    {
        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);

        $minMaxPricesFactory = $this->createMock(MinMaxPricesInterfaceFactory::class);
        $minMaxPricesFactory->expects($this->once())
            ->method('create')
            ->willReturn($minMaxPrices);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getSubProducts')->willReturn([]);

        $configurable = $this->createObjectToTest(minMaxPricesFactory: $minMaxPricesFactory);

        $result = $this->invokeMethod($configurable, 'getChildrenMinMaxPrices', [$pricingContext]);

        $this->assertSame($minMaxPrices, $result);
    }

    /**
     * Test getChildrenMinMaxPrices returns same value for all when no subProducts
     */
    public function testGetChildrenMinMaxPricesReturnsMaxFloatWhenNoSubProducts(): void
    {
        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);

        $minMaxPricesFactory = $this->createMock(MinMaxPricesInterfaceFactory::class);
        $minMaxPricesFactory->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                // When no subProducts, min starts at PHP_FLOAT_MAX and all become equal
                return $data['min'] === $data['max']
                    && $data['min'] === $data['minOriginal']
                    && $data['min'] === $data['maxOriginal'];
            }))
            ->willReturn($minMaxPrices);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('getSubProducts')->willReturn([]);

        $configurable = $this->createObjectToTest(minMaxPricesFactory: $minMaxPricesFactory);

        $result = $this->invokeMethod($configurable, 'getChildrenMinMaxPrices', [$pricingContext]);

        $this->assertSame($minMaxPrices, $result);
    }

    /**
     * Test addOriginalPrice (AbstractProductWithChildren) sets original when max < maxOriginal
     */
    public function testAddOriginalPriceSetsOriginalWhenDiscounted(): void
    {
        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);
        $minMaxPrices->method('getMin')->willReturn(50.00);
        $minMaxPrices->method('getMax')->willReturn(80.00);
        $minMaxPrices->method('getMinOriginal')->willReturn(55.00);
        $minMaxPrices->method('getMaxOriginal')->willReturn(100.00); // Higher than max

        $priceData = $this->createMock(PriceDataInterface::class);
        $priceData->method('getPrice')->willReturn(60.00);

        // Should set original prices
        $priceData->expects($this->once())
            ->method('setOriginalPrice')
            ->with(100.00);

        $priceData->expects($this->once())
            ->method('setMinOriginalPrice')
            ->with(55.00);

        $configurable = $this->createObjectToTest();

        $result = $this->invokeMethod($configurable, 'addOriginalPrice', [$priceData, $minMaxPrices]);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test addOriginalPrice does not set original when no discount
     */
    public function testAddOriginalPriceDoesNotSetOriginalWhenNoDiscount(): void
    {
        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);
        $minMaxPrices->method('getMin')->willReturn(50.00);
        $minMaxPrices->method('getMax')->willReturn(80.00);
        $minMaxPrices->method('getMinOriginal')->willReturn(50.00);
        $minMaxPrices->method('getMaxOriginal')->willReturn(80.00); // Same as max

        $priceData = $this->createMock(PriceDataInterface::class);
        $priceData->method('getPrice')->willReturn(60.00);

        // Should NOT set original prices
        $priceData->expects($this->never())->method('setOriginalPrice');
        $priceData->expects($this->never())->method('setMinOriginalPrice');

        $configurable = $this->createObjectToTest();

        $result = $this->invokeMethod($configurable, 'addOriginalPrice', [$priceData, $minMaxPrices]);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test addOriginalPrice sets price to min when current price is zero
     */
    public function testAddOriginalPriceSetsPriceToMinWhenZero(): void
    {
        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);
        $minMaxPrices->method('getMin')->willReturn(50.00);
        $minMaxPrices->method('getMax')->willReturn(80.00);
        $minMaxPrices->method('getMinOriginal')->willReturn(50.00);
        $minMaxPrices->method('getMaxOriginal')->willReturn(80.00);

        $priceData = $this->createMock(PriceDataInterface::class);
        $priceData->method('getPrice')->willReturn(0.00);

        // Should set price to min
        $priceData->expects($this->once())
            ->method('setPrice')
            ->with(50.00);

        $configurable = $this->createObjectToTest();

        $result = $this->invokeMethod($configurable, 'addOriginalPrice', [$priceData, $minMaxPrices]);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test addPriceRange (AbstractProductWithChildren) delegates to strategy
     */
    public function testAddPriceRangeDelegatesToStrategy(): void
    {
        $priceData = $this->createStub(PriceDataInterface::class);
        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);
        $pricingContext = $this->createStub(PricingContextInterface::class);

        $defaultPricingStrategy = $this->createMock(DefaultPricingStrategy::class);
        $defaultPricingStrategy->expects($this->once())
            ->method('applyPriceRange')
            ->with($priceData, $pricingContext, $minMaxPrices)
            ->willReturn($priceData);

        $configurable = $this->createObjectToTest(defaultPricingStrategy: $defaultPricingStrategy);

        // Set the strategy (normally done by calculatePriceData)
        $this->setPrivateProperty($configurable, 'pricingStrategy', $defaultPricingStrategy);

        $result = $this->invokeMethod($configurable, 'addPriceRange', [$priceData, $pricingContext, $minMaxPrices]);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test addChildrenCustomerGroupsPrices (AbstractProductWithChildren) returns unchanged when disabled
     */
    public function testAddChildrenCustomerGroupsPricesReturnsUnchangedWhenDisabled(): void
    {
        $priceData = $this->createStub(PriceDataInterface::class);
        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('areCustomerGroupsEnabled')->willReturn(false);

        $configurable = $this->createObjectToTest();

        $result = $this->invokeMethod($configurable, 'addChildrenCustomerGroupsPrices', [
            $priceData,
            $pricingContext,
            $minMaxPrices
        ]);

        $this->assertSame($priceData, $result);
    }

    /**
     * Test addChildrenCustomerGroupsPrices delegates to strategy when enabled
     */
    public function testAddChildrenCustomerGroupsPricesDelegatesToStrategyWhenEnabled(): void
    {
        $priceData = $this->createStub(PriceDataInterface::class);
        $minMaxPrices = $this->createStub(MinMaxPricesInterface::class);
        $minMaxPrices->method('getMin')->willReturn(50.00);

        $pricingContext = $this->createStub(PricingContextInterface::class);
        $pricingContext->method('areCustomerGroupsEnabled')->willReturn(true);

        $customerGroupPricingStrategy = $this->createMock(CustomerGroupPricingStrategy::class);
        $customerGroupPricingStrategy->expects($this->once())
            ->method('applyChildrenCustomerGroupPrices')
            ->with($priceData, $pricingContext, $minMaxPrices)
            ->willReturn($priceData);

        $customerGroupPricingStrategy->expects($this->once())
            ->method('applyCustomerGroupsFinalPrices')
            ->with($priceData, $pricingContext, 50.00)
            ->willReturn($priceData);

        $configurable = $this->createObjectToTest(customerGroupPricingStrategy: $customerGroupPricingStrategy);

        // Set the strategy (normally done by calculatePriceData)
        $this->setPrivateProperty($configurable, 'pricingStrategy', $customerGroupPricingStrategy);

        $result = $this->invokeMethod($configurable, 'addChildrenCustomerGroupsPrices', [
            $priceData,
            $pricingContext,
            $minMaxPrices
        ]);

        $this->assertSame($priceData, $result);
    }
}
