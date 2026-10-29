# Pricing V1 vs V2 Integration Tests

Compares old pricing implementation (V1 - Helper-based) vs new implementation (V2 - Service-based).

**V1**: `Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager`  
**V2**: `Algolia\AlgoliaSearch\Service\Product\PricingManagerV2`

## Test Coverage

All test classes extend `AbstractPricingV1VsV2TestCase` which provides shared comparison utilities.

### DefaultPricingV1VsV2Test
Tests **default/basic pricing scenarios** without customer groups, special prices, catalog rules, or tier prices. Validates core pricing logic across all product types.

**Tests**: 6 product types (simple x2, configurable x2, bundle, grouped)

### CustomerGroupPricingV1VsV2Test
Tests **customer group pricing** with multiple customer groups enabled. Validates that group-specific prices are calculated identically by V1 and V2.

**Tests**: 6 product types (simple x2, configurable x2, bundle, grouped) with customer groups enabled

### SpecialPricePricingV1VsV2Test
Tests **special prices** (sale prices, discounts). Validates that special pricing, original price tracking, and date-based price validity work identically in V1 and V2.

**Tests**: 5 scenarios
- Simple product with special price (programmatically created)
- Configurable product with child variants having special prices
- Bundle product with bundle selections having special prices
- Grouped product with associated products having special prices (uses 24-WG085_Group)
- Simple product with expired special price (date validation)

**Key Features**:
- Applies special prices to **child/associated products** for complex product types
- Creates test products programmatically for simple products to ensure active special prices
- Uses sample data for complex products (configurable, bundle, grouped) and modifies their children
- Tests realistic scenarios: discounted variants, on-sale bundle items, grouped products with mixed discounts

## Product SKUs Used

**Sample Data Products**:
- Simple: 24-MB01, 24-MB04
- Configurable: MH01, WS03
- Bundle: 24-WG080
- Grouped: 24-WG085 (default/customer group tests), 24-WG085_Group (special price tests)

**Programmatically Created** (SpecialPricePricingV1VsV2Test only):
- test-simple-special-price (simple with active special price)
- test-simple-expired-special (simple with expired special price)

## What Each Test Validates

**Default & Customer Group Tests**:
- Price fields (price, price_with_tax)
- Multiple currencies
- Customer group-specific prices (when enabled)
- Tax configurations
- Base pricing logic without discounts

**Special Price Tests**:
- Special prices applied to child products (configurable variants, bundle selections, grouped associated products)
- Multiple discount levels on different children (tests price aggregation)
- Original price preservation
- Date-based special price validity (expired vs active)
- Min/max price calculations with mixed discounts

**Important**: Tests force pricing engine config to V1 to prevent V1 from delegating to V2 (which would create false positives). This ensures genuine comparison between the two implementations.

## Requirements

- Magento sample data installed
- Both V1 and V2 implementations (epic/MAGE-1705-pricing-v2 branch)

## Running Tests

```bash
# Run ALL pricing comparison tests (default + customer groups)
vendor/bin/phpunit \
  -c dev/tests/integration/phpunit.xml.dist \
  --group algolia_pricing_comparison \
  vendor/algolia/algoliasearch-magento-2/Test/Integration/Indexing/Product/Pricing/

# Run only default pricing tests
vendor/bin/phpunit \
  -c dev/tests/integration/phpunit.xml.dist \
  --filter DefaultPricingV1VsV2Test \
  vendor/algolia/algoliasearch-magento-2/Test/Integration/Indexing/Product/Pricing/

# Run only customer group pricing tests
vendor/bin/phpunit \
  -c dev/tests/integration/phpunit.xml.dist \
  --filter CustomerGroupPricingV1VsV2Test \
  vendor/algolia/algoliasearch-magento-2/Test/Integration/Indexing/Product/Pricing/

# Run only special price tests
vendor/bin/phpunit \
  -c dev/tests/integration/phpunit.xml.dist \
  --filter SpecialPricePricingV1VsV2Test \
  vendor/algolia/algoliasearch-magento-2/Test/Integration/Indexing/Product/Pricing/

# Run specific test method
vendor/bin/phpunit \
  -c dev/tests/integration/phpunit.xml.dist \
  --filter DefaultPricingV1VsV2Test::testSimpleProduct \
  vendor/algolia/algoliasearch-magento-2/Test/Integration/Indexing/Product/Pricing/

# Run by group tag
vendor/bin/phpunit \
  -c dev/tests/integration/phpunit.xml.dist \
  --group algolia_pricing_default \
  vendor/algolia/algoliasearch-magento-2/Test/Integration/Indexing/Product/Pricing/

vendor/bin/phpunit \
  -c dev/tests/integration/phpunit.xml.dist \
  --group algolia_pricing_customer_groups \
  vendor/algolia/algoliasearch-magento-2/Test/Integration/Indexing/Product/Pricing/

vendor/bin/phpunit \
  -c dev/tests/integration/phpunit.xml.dist \
  --group algolia_pricing_special \
  vendor/algolia/algoliasearch-magento-2/Test/Integration/Indexing/Product/Pricing/
```

## Sample Data

Install if not present:
```bash
bin/magento sampledata:deploy
bin/magento setup:upgrade
```

Tests automatically skip when products are not found.

## Special Price Test Methodology

The special price tests apply discounts to **child/associated products** rather than parent products, testing realistic e-commerce scenarios:

**Configurable Products**:
- 20% discount on first child variant (e.g., size S in red)
- 15% discount on second child variant (e.g., size M in blue)
- Tests that V1 and V2 correctly aggregate variant pricing into parent min/max prices

**Bundle Products**:
- 25% discount on first bundle selection
- 10% discount on second bundle selection
- Tests that V1 and V2 correctly sum bundle options with mixed discounts

**Grouped Products**:
- 30% discount on first associated product
- 15% discount on second associated product
- 10% discount on third associated product
- Tests that V1 and V2 correctly identify the lowest price among associated products

This approach validates the most complex pricing scenarios where child products have different discount levels, ensuring V1→V2 migration handles real-world pricing complexity correctly.

## What's Compared

For each product:
- Same price fields (price, price_with_tax)
- Same currencies in each field
- All price values match (±0.001 tolerance after rounding to 2 decimals)
- Customer group prices match
- Special prices, tier prices, min prices match

**Notes**: 
- Keys ending with `_max` are V1-only fields and are intentionally excluded from comparison (expected architectural difference)
- Key `default_original` is excluded from comparison (architectural difference between V1 and V2)
- Price values are rounded to 2 decimal places (cents) before comparison to handle float precision differences from different calculation methods (V1 uses Magento's native PriceInfo, V2 manually sums option prices for bundles)

## Troubleshooting

### All tests skipped
Sample data not installed. Run:
```bash
bin/magento sampledata:deploy && bin/magento setup:upgrade
```

### "Pricing mismatch"
V2 produces different results than V1. Check error output for:
- Specific field that differs
- V1 vs V2 values
- Difference amount

Float tolerance: ±0.001 - larger differences indicate bugs.
