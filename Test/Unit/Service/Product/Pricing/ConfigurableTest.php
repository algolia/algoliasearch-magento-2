<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterfaceFactory;
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
}
