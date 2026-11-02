# Pricing V1 vs V2 Integration Tests

Comprehensive test suite comparing old pricing implementation (V1 - Helper-based) vs new implementation (V2 - Service-based).

**V1**: `Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager`  
**V2**: `Algolia\AlgoliaSearch\Service\Product\PricingManagerV2`

**Current Coverage**: **52 tests across 6 test classes** covering all critical pricing scenarios

---

## Test Classes Overview

All test classes extend `AbstractPricingV1VsV2TestCase` which provides shared comparison utilities.

### 1. DefaultPricingV1VsV2Test (6 tests)
Tests **default/basic pricing scenarios** without customer groups, special prices, catalog rules, or tier prices. Validates core pricing logic across all product types.

**Coverage**:
- Simple products (2 SKUs: 24-MB01, 24-MB04)
- Configurable products (2 SKUs: MH01, WS03)
- Bundle product (1 SKU: 24-WG080)
- Grouped product (1 SKU: 24-WG085)

**What it validates**: Base pricing calculation, price field structure, multi-currency support

---

### 2. CustomerGroupPricingV1VsV2Test (6 tests)
Tests **customer group pricing** with multiple customer groups enabled. Validates that group-specific prices are calculated identically by V1 and V2.

**Coverage**: Same 6 product types with customer groups enabled (General, Wholesale, Retailer)

**What it validates**: 
- Customer group-specific price calculation
- Strategy pattern selection (DefaultPricingStrategy vs CustomerGroupPricingStrategy)
- CustomerGroupsManager filtering logic

---

### 3. SpecialPricePricingV1VsV2Test (5 tests)
Tests **special prices** (sale prices, discounts). Validates that special pricing, original price tracking, and date-based price validity work identically in V1 and V2.

**Coverage**:
- Simple product with special price (programmatically created)
- Configurable product with child variants having special prices (20%, 15% discounts)
- Bundle product with bundle selections having special prices (25%, 10% discounts)
- Grouped product with associated products having special prices (30%, 15%, 10% discounts)
- Simple product with expired special price (date validation)

**Key Features**:
- Applies special prices to **child/associated products** for realistic scenarios
- Creates test products programmatically to ensure active special prices
- Uses sample data products and modifies their children
- Tests mixed discount scenarios on complex products

**Test Methodology**: Special prices are applied to child products (variants, selections, associated) rather than parents, validating real-world e-commerce pricing where individual SKUs are discounted.

---

### 4. TierPricingV1VsV2Test (9 tests) ✨
Tests **tier prices** (quantity-based pricing). Validates that tier pricing with customer group variations works identically in V1 and V2.

**Coverage**:
- Simple with basic tier prices (qty breaks: 10+, 50+, 100+)
- Simple with customer group-specific tier prices
- Tier price combined with special price (tier wins)
- Special price wins over tier price
- Configurable with tier prices on child variants
- Bundle with tier prices on selections
- Grouped with tier prices on associated products
- Multiple tier prices selecting lowest
- Group-specific tier overrides "All Groups"

**Key Features**:
- Creates tier prices programmatically via `ProductTierPriceInterface`
- Tests quantity-based pricing (B2B essential feature)
- Validates tier/special price interaction (lowest wins)
- Tests "All Groups" vs group-specific precedence

---

### 5. CatalogRulePricingV1VsV2Test (12 tests) ✨
Tests **catalog price rules** (promotional discounts). Validates that catalog rules with priority, customer groups, and date ranges work identically in V1 and V2.

**Coverage**:
- Simple with percentage discount (20% off)
- Simple with fixed amount discount ($15 off)
- Catalog rule with customer group restrictions (Wholesale only)
- Expired catalog rule not applied (date validation)
- Active catalog rule within date range
- Catalog rule wins over special price
- Special price wins over catalog rule
- Multiple catalog rules with priority handling
- Configurable product with catalog rule
- Bundle product with catalog rule
- Grouped product with catalog rule

**Key Features**:
- Creates catalog rules programmatically via `RuleFactory` and `CatalogRuleRepository`
- Runs catalog rule indexer (`RuleProductIndexer->executeFull()`)
- Tests rule priority and "stop processing" logic
- Validates interaction with special/tier prices (lowest wins)

**Technical Notes**:
- Uses `@magentoDbIsolation disabled` to allow DDL operations during indexing
- Uses `@magentoAppArea adminhtml` for proper admin context
- Cleanup in `tearDown()` removes created rules and products

---

### 6. TaxConfigurationV1VsV2Test (14 tests) ✨
Tests **tax calculation and display modes**. Validates that tax calculation with different display modes and FPT works identically in V1 and V2.

**Coverage**:
- Prices excluding tax (DISPLAY_TYPE_EXCLUDING_TAX)
- Prices including tax (DISPLAY_TYPE_INCLUDING_TAX)
- Display both prices (DISPLAY_TYPE_BOTH - creates both `price` and `price_with_tax` fields)
- Tax with different rate (20%)
- Tax on configurable product
- Tax on grouped product
- Tax with special price
- Tax with tier price
- FPT (Fixed Product Tax / Weee) enabled
- FPT with special price
- Cross-border tax scenario (catalog prices include tax)
- Tax calculation disabled

**Key Features**:
- Creates tax rates and rules programmatically via `TaxRateRepository` and `TaxRuleRepository`
- Assigns products to tax class ID 2 (Taxable Goods)
- Tests all three tax display modes
- Validates FPT (Fixed Product Tax) for environmental fees/deposits
- Saves and restores original tax configuration

**Tax Display Modes**:
- **DISPLAY_TYPE_EXCLUDING_TAX (1)**: Shows prices without tax, only `price` field
- **DISPLAY_TYPE_INCLUDING_TAX (2)**: Shows prices with tax included, only `price` field
- **DISPLAY_TYPE_BOTH (3)**: Shows both `price` (excl. tax) and `price_with_tax` (incl. tax) fields

---

## Product SKUs Used

### Sample Data Products
- **Simple**: 24-MB01, 24-MB04
- **Configurable**: MH01, WS03
- **Bundle**: 24-WG080
- **Grouped**: 24-WG085 (default/customer group tests), 24-WG085_Group (special price/tier price tests)

### Programmatically Created Products
Various test-specific SKUs are created programmatically:
- **SpecialPricePricingV1VsV2Test**: `test-simple-special-price`, `test-simple-expired-special`
- **TierPricingV1VsV2Test**: `test-simple-tier-price`, `test-tier-with-special`, etc.
- **CatalogRulePricingV1VsV2Test**: `test-catalog-rule-percent`, `test-catalog-rule-fixed`, etc.
- **TaxConfigurationV1VsV2Test**: `test-tax-special`, `test-tax-tier`, `test-tax-fpt`, etc.

Products created programmatically are automatically cleaned up via Magento's test framework transaction rollback (or explicit cleanup in `tearDown()` for tests with `@magentoDbIsolation disabled`).

---

## What Each Test Validates

### Core Pricing Tests (Default & Customer Group)
✅ Price fields (`price`, `price_with_tax`) generated correctly  
✅ Multiple currencies calculated identically  
✅ Customer group-specific prices match  
✅ Tax configurations applied correctly  
✅ Base pricing logic without discounts  

### Special Price Tests
✅ Special prices applied to child products (variants, selections, associated)  
✅ Multiple discount levels on different children  
✅ Original price preservation (`default_original`)  
✅ Date-based special price validity (expired vs active)  
✅ Min/max price calculations with mixed discounts  

### Tier Price Tests
✅ Quantity-based pricing (10+, 50+, 100+ breaks)  
✅ Customer group-specific tier prices  
✅ "All Groups" vs group-specific tier precedence  
✅ Tier price + special price interaction (lowest wins)  
✅ Tier prices on complex products (child variants/selections)  

### Catalog Rule Tests
✅ Percentage and fixed amount discounts  
✅ Customer group restrictions  
✅ Date-based rule activation (active vs expired)  
✅ Rule priority and "stop processing" logic  
✅ Catalog rule + special/tier price interaction (lowest wins)  
✅ Multiple rules applying to same product  

### Tax Configuration Tests
✅ Tax display modes (excluding, including, both)  
✅ Different tax rates (10%, 20%)  
✅ Price field generation based on display mode  
✅ Tax calculation on discounted prices  
✅ FPT (Fixed Product Tax / Weee) integration  
✅ Cross-border scenarios (prices include tax)  

---

## Architectural Validation

**Important**: Tests force pricing engine config to V1 to prevent V1 from delegating to V2 (which would create false positives). This ensures genuine comparison between the two implementations.

```php
$this->mutableConfig->setValue(
    'algoliasearch_products/products/pricing_engine',
    Pricing::PRICING_V1,
    ScopeInterface::SCOPE_STORE
);
```

### What's Compared

For each product, V1 and V2 results are compared:
- ✅ Same price fields present (`price`, `price_with_tax`)
- ✅ Same currencies in each field
- ✅ All price values match (±0.001 tolerance after rounding to 2 decimals)
- ✅ Customer group prices match
- ✅ Special prices, tier prices, min prices match
- ✅ Original prices preserved correctly

**Excluded from comparison** (expected architectural differences):
- Keys ending with `_max` (V1-only fields)
- Key `default_original` (different handling between V1/V2)

**Float precision handling**: Price values are rounded to 2 decimal places (cents) before comparison to handle float precision differences from different calculation methods.

---

## Requirements

- **Magento 2.4.6+** with sample data installed
- **PHP 8.2+**
- Both V1 and V2 implementations available
- Branch: `epic/MAGE-1705-pricing-v2`

---

## Running Tests

### Run All Pricing Tests
```bash
vendor/bin/phpunit \
  -c dev/tests/integration/phpunit.xml.dist \
  --group algolia_pricing_comparison \
  vendor/algolia/algoliasearch-magento-2/Test/Integration/Indexing/Product/Pricing/
```

## Test Methodology Details

### Special Price Test Approach
Special price tests apply discounts to **child/associated products** rather than parent products, validating realistic e-commerce scenarios:

**Configurable Products**:
- 20% discount on first child variant (e.g., size S in red)
- 15% discount on second child variant (e.g., size M in blue)
- Validates V1 and V2 correctly aggregate variant pricing into parent min/max prices

**Bundle Products**:
- 25% discount on first bundle selection
- 10% discount on second bundle selection
- Validates V1 and V2 correctly sum bundle options with mixed discounts

**Grouped Products**:
- 30% discount on first associated product
- 15% discount on second associated product
- 10% discount on third associated product
- Validates V1 and V2 correctly identify the lowest price among associated products

This approach tests the most complex pricing scenarios where child products have different discount levels, ensuring V1→V2 migration handles real-world pricing complexity correctly.

### Tier Price Test Approach
Tier prices are created programmatically using `ProductTierPriceInterface`:
- Quantity-based discounts (buy 10+ save $X, buy 50+ save $Y)
- Customer group-specific tier prices (Wholesale gets better pricing)
- "All Groups" tier prices vs group-specific (group-specific wins)
- Tier + special price interaction (lowest price wins)

### Catalog Rule Test Approach
Catalog rules are created programmatically and applied via indexer:
- Rules created via `RuleFactory` and saved via `CatalogRuleRepository`
- Indexer run via `RuleProductIndexer->executeFull()` to apply rules
- Tests use `@magentoDbIsolation disabled` to allow DDL operations during indexing
- Rules support percentage discounts, fixed amount discounts, customer groups, date ranges

### Tax Configuration Test Approach
Tax rates and rules are created programmatically:
- Tax rates created via `TaxRateRepository` (e.g., 10% US tax)
- Tax rules created via `TaxRuleRepository` linking rates to product/customer tax classes
- Products assigned to tax class ID 2 (Taxable Goods)
- Original tax config saved in `setUp()` and restored in `tearDown()`
- Tests all three display modes: excluding, including, both

---
