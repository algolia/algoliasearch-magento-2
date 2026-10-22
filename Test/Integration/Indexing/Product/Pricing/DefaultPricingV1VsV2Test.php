<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\Pricing;

/**
 * Integration tests comparing Pricing V1 vs V2 for DEFAULT scenarios
 *
 * Tests basic/default pricing behavior WITHOUT:
 * - Customer group prices (only default group tested)
 * - Special prices / catalog rules
 * - Tier prices
 * - Advanced tax configurations
 *
 * Validates that V2 produces identical results to V1 across:
 * - All product types (simple, configurable, bundle, grouped)
 * - Multiple currencies
 * - Basic tax configurations
 *
 * Uses Magento sample data products, skips tests gracefully if not found.
 *
 * @group algolia_pricing
 * @group algolia_pricing_comparison
 * @group algolia_pricing_default
 */
class DefaultPricingV1VsV2Test extends AbstractPricingV1VsV2TestCase
{

    public function testSimpleProduct(): void
    {
        $this->assertPricingMatches('24-MB01');
    }

    public function testSimpleProductAlternative(): void
    {
        $this->assertPricingMatches('24-MB04');
    }

    public function testConfigurableProduct(): void
    {
        $this->assertPricingMatches('MH01');
    }

    public function testConfigurableProductAlternative(): void
    {
        $this->assertPricingMatches('WS03');
    }

    public function testBundleProduct(): void
    {
        $this->assertPricingMatches('24-WG080');
    }

    public function testGroupedProduct(): void
    {
        $this->assertPricingMatches('24-WG085');
    }
}
