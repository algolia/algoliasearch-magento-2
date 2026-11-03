<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterfaceFactory;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\Product\CatalogRulesManager;
use Algolia\AlgoliaSearch\Service\Product\PriceDataFormatter;
use Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups\CustomerGroupsManager;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Grouped;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\CustomerGroupPricingStrategy;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\DefaultPricingStrategy;
use Algolia\AlgoliaSearch\Test\TestCase;

/**
 * @covers \Algolia\AlgoliaSearch\Service\Product\Pricing\Grouped
 */
class GroupedTest extends TestCase
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
    ): Grouped {
        return new Grouped(
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
     * Test that Grouped can be instantiated with all dependencies
     *
     * Note: Grouped extends AbstractProductWithChildren with no custom logic.
     * AbstractProductWithChildren methods are tested through ConfigurableTest.
     */
    public function testCanBeInstantiatedWithDependencies(): void
    {
        $grouped = $this->createObjectToTest();

        $this->assertInstanceOf(Grouped::class, $grouped);
    }
}
