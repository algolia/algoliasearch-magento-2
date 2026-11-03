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
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\CustomerGroupPricingStrategy;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\DefaultPricingStrategy;
use Algolia\AlgoliaSearch\Test\TestCase;

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
        ?DefaultPricingStrategy $defaultPricingStrategy = null,
        ?CustomerGroupPricingStrategy $customerGroupPricingStrategy = null
    ): Simple {
        return new Simple(
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
        $defaultPricingStrategy = $this->createStub(DefaultPricingStrategy::class);
        $customerGroupPricingStrategy = $this->createStub(CustomerGroupPricingStrategy::class);

        $simple = new Simple(
            $configHelper,
            $pricingHelper,
            $logger,
            $priceDataFormatter,
            $catalogRulesManager,
            $customerGroupsManager,
            $defaultPricingStrategy,
            $customerGroupPricingStrategy
        );

        $this->assertInstanceOf(Simple::class, $simple);
    }
}
