<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\Pricing;

use Magento\Store\Model\ScopeInterface;

/**
 * Integration tests comparing Pricing V1 vs V2 with CUSTOMER GROUPS enabled
 *
 * Tests customer group pricing behavior with:
 * - Multiple customer groups (General, Wholesale, Retailer)
 * - Group-specific prices
 * - Tier prices per group
 * - Special prices per group
 *
 * Validates that V2 produces identical results to V1 for:
 * - All product types with group prices
 * - Multiple customer groups
 * - Multiple currencies
 *
 * Uses Magento sample data products, skips tests gracefully if not found.
 *
 * @group algolia_pricing
 * @group algolia_pricing_comparison
 * @group algolia_pricing_customer_groups
 */
class CustomerGroupPricingV1VsV2Test extends AbstractPricingV1VsV2TestCase
{
    private const CONFIG_PATH_CUSTOMER_GROUPS_ENABLE = 'algoliasearch_advanced/advanced/customer_groups_enable';

    protected function setUp(): void
    {
        parent::setUp();

        // Enable customer groups pricing
        $this->mutableConfig->setValue(
            self::CONFIG_PATH_CUSTOMER_GROUPS_ENABLE,
            '1',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Test simple product with customer group prices
     *
     * Sample data products often have tier/group pricing configured
     */
    public function testSimpleProductWithGroupPrices(): void
    {
        $this->assertPricingMatches('24-MB01');
    }

    public function testSimpleProductWithGroupPricesAlternative(): void
    {
        $this->assertPricingMatches('24-MB04');
    }

    /**
     * Test configurable product with customer group prices
     */
    public function testConfigurableProductWithGroupPrices(): void
    {
        $this->assertPricingMatches('MH01');
    }

    public function testConfigurableProductWithGroupPricesAlternative(): void
    {
        $this->assertPricingMatches('WS03');
    }

    /**
     * Test bundle product with customer group prices
     */
    public function testBundleProductWithGroupPrices(): void
    {
        $this->assertPricingMatches('24-WG080');
    }

    /**
     * Test grouped product with customer group prices
     */
    public function testGroupedProductWithGroupPrices(): void
    {
        $this->assertPricingMatches('24-WG085');
    }
}
