<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\Pricing;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\Data\TaxRuleInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use Magento\Tax\Model\ClassModel;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Integration tests comparing Pricing V1 vs V2 with TAX CONFIGURATIONS
 *
 * Tests tax calculation and display behavior with:
 * - Tax display modes (excluding tax, including tax, display both)
 * - Different tax rates (10%, 20%)
 * - Price field generation (price vs price_with_tax)
 * - FPT (Fixed Product Tax / Weee) integration
 * - Tax interactions with special prices, tier prices, catalog rules
 * - Tax on complex products (configurable, bundle, grouped)
 *
 * Validates that V2 produces identical results to V1 for:
 * - Tax calculation accuracy
 * - Correct price fields based on display mode
 * - FPT inclusion/exclusion in prices
 * - Tax applied to discounted prices
 * - Tax on bundle/configurable/grouped products
 *
 * Tax Display Modes:
 * - DISPLAY_TYPE_EXCLUDING_TAX (1): Shows prices without tax, only "price" field
 * - DISPLAY_TYPE_INCLUDING_TAX (2): Shows prices with tax included, only "price" field
 * - DISPLAY_TYPE_BOTH (3): Shows both prices, "price" and "price_with_tax" fields
 *
 * Note: Tax configuration changes affect global state. Tests modify store config
 * and restore original values in tearDown().
 *
 * @group algolia_pricing
 * @group algolia_pricing_comparison
 * @group algolia_pricing_tax
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class TaxConfigurationV1VsV2Test extends AbstractPricingV1VsV2TestCase
{
    protected const CONFIG_PATH_INDEXING_ENABLED = 'algoliasearch_indexing_manager/algolia_indexing/enable_indexing';

    protected ?ScopeConfigInterface $scopeConfig = null;
    private ?TaxRateRepositoryInterface $taxRateRepository = null;
    private ?TaxRuleRepositoryInterface $taxRuleRepository = null;

    /** @var array<string, mixed> Original config values to restore */
    private array $originalConfigValues = [];

    /** @var array<int> Created tax rate IDs for cleanup */
    private array $createdTaxRateIds = [];

    /** @var array<int> Created tax rule IDs for cleanup */
    private array $createdTaxRuleIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $objectManager = Bootstrap::getObjectManager();
        $this->scopeConfig = $objectManager->get(ScopeConfigInterface::class);
        $this->taxRateRepository = $objectManager->get(TaxRateRepositoryInterface::class);
        $this->taxRuleRepository = $objectManager->get(TaxRuleRepositoryInterface::class);

        // Disable Algolia indexing
        $this->mutableConfig->setValue(self::CONFIG_PATH_INDEXING_ENABLED, 0, ScopeInterface::SCOPE_STORE);

        // Save original tax configuration
        $this->saveOriginalTaxConfig();
    }

    protected function tearDown(): void
    {
        // Clean up created tax rules
        foreach ($this->createdTaxRuleIds as $ruleId) {
            try {
                $this->taxRuleRepository->deleteById($ruleId);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Clean up created tax rates
        foreach ($this->createdTaxRateIds as $rateId) {
            try {
                $this->taxRateRepository->deleteById($rateId);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Restore original tax configuration
        $this->restoreOriginalTaxConfig();

        // Re-enable Algolia indexing
        $this->mutableConfig->setValue(self::CONFIG_PATH_INDEXING_ENABLED, 1, ScopeInterface::SCOPE_STORE);

        $this->createdTaxRateIds = [];
        $this->createdTaxRuleIds = [];

        parent::tearDown();
    }

    /**
     * Test prices excluding tax (DISPLAY_TYPE_EXCLUDING_TAX)
     *
     * Display mode: Show prices without tax
     * Expected: Only "price" field, no "price_with_tax"
     * Tax rate: 10%
     * Product: $100 → Displayed: $100 (tax not shown)
     */
    public function testPricesExcludingTax(): void
    {
        $this->configureTax([
            'display_type' => TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX,
            'tax_rate' => 10.0,
        ]);

        $product = $this->loadProductOrSkip('24-MB01');
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test prices including tax (DISPLAY_TYPE_INCLUDING_TAX)
     *
     * Display mode: Show prices with tax included
     * Expected: Only "price" field with tax included, no "price_with_tax"
     * Tax rate: 10%
     * Product: $100 → Displayed: $110 (tax included in price)
     */
    public function testPricesIncludingTax(): void
    {
        $this->configureTax([
            'display_type' => TaxConfig::DISPLAY_TYPE_INCLUDING_TAX,
            'tax_rate' => 10.0,
        ]);

        $product = $this->loadProductOrSkip('24-MB01');
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test display both prices (DISPLAY_TYPE_BOTH)
     *
     * Display mode: Show both prices (with and without tax)
     * Expected: Both "price" (excl. tax) and "price_with_tax" (incl. tax) fields
     * Tax rate: 10%
     * Product: $100 → price: $100, price_with_tax: $110
     */
    public function testDisplayBothPrices(): void
    {
        $this->configureTax([
            'display_type' => TaxConfig::DISPLAY_TYPE_BOTH,
            'tax_rate' => 10.0,
        ]);

        $product = $this->loadProductOrSkip('24-MB01');
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test tax with different rate (20%)
     *
     * Display mode: Display both
     * Tax rate: 20%
     * Product: $100 → price: $100, price_with_tax: $120
     */
    public function testTaxWithDifferentRate(): void
    {
        $this->configureTax([
            'display_type' => TaxConfig::DISPLAY_TYPE_BOTH,
            'tax_rate' => 20.0,
        ]);

        $product = $this->loadProductOrSkip('24-MB01');
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test tax on configurable product
     *
     * Tax should apply to child variant prices
     */
    public function testTaxOnConfigurableProduct(): void
    {
        $this->configureTax([
            'display_type' => TaxConfig::DISPLAY_TYPE_BOTH,
            'tax_rate' => 10.0,
        ]);

        $product = $this->loadProductOrSkip('MH01');
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test tax on grouped product
     *
     * Tax should apply to associated product prices
     */
    public function testTaxOnGroupedProduct(): void
    {
        $this->configureTax([
            'display_type' => TaxConfig::DISPLAY_TYPE_BOTH,
            'tax_rate' => 10.0,
        ]);

        $product = $this->loadProductOrSkip('24-WG085_Group');
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test tax with special price
     *
     * Tax should apply to discounted (special) price
     * Base: $100, Special: $80, Tax: 10%
     * Expected: price: $80, price_with_tax: $88
     */
    public function testTaxWithSpecialPrice(): void
    {
        $this->configureTax([
            'display_type' => TaxConfig::DISPLAY_TYPE_BOTH,
            'tax_rate' => 10.0,
        ]);

        $product = $this->createSimpleProductWithSpecialPrice(
            'test-tax-special',
            100.00,
            80.00
        );

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test tax with tier price
     *
     * Tax should apply to tier price when tier is lowest
     * Base: $100, Tier (10+): $85, Tax: 10%
     * Expected tier price: $85, with tax: $93.50
     */
    public function testTaxWithTierPrice(): void
    {
        $this->configureTax([
            'display_type' => TaxConfig::DISPLAY_TYPE_BOTH,
            'tax_rate' => 10.0,
        ]);

        $product = $this->createSimpleProductWithTierPrices(
            'test-tax-tier',
            100.00,
            [
                ['qty' => 10, 'price' => 85.00, 'group' => 0],
            ]
        );

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test FPT (Fixed Product Tax / Weee) enabled
     *
     * FPT is displayed separately from regular price
     * Base: $100, FPT: $5, Tax: 10%
     *
     * Note: FPT behavior depends on Weee configuration
     */
    public function testFptEnabled(): void
    {
        $this->configureTax([
            'display_type' => TaxConfig::DISPLAY_TYPE_BOTH,
            'tax_rate' => 10.0,
        ]);

        $this->configureFpt([
            'enabled' => true,
            'amount' => 5.00,
        ]);

        $product = $this->createSimpleProductWithFpt(
            'test-tax-fpt',
            100.00,
            5.00
        );

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test FPT combined with special price
     *
     * FPT applies to product even with special price
     * Base: $100, Special: $80, FPT: $5, Tax: 10%
     */
    public function testFptWithSpecialPrice(): void
    {
        $this->configureTax([
            'display_type' => TaxConfig::DISPLAY_TYPE_BOTH,
            'tax_rate' => 10.0,
        ]);

        $this->configureFpt([
            'enabled' => true,
            'amount' => 5.00,
        ]);

        $product = $this->createSimpleProductWithFpt(
            'test-tax-fpt-special',
            100.00,
            5.00
        );
        $product->setSpecialPrice(80.00);
        $this->productRepository->save($product);

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test cross-border tax scenario (catalog prices include tax)
     *
     * Catalog prices include tax: YES
     * Display: Both prices
     * Tax rate: 20%
     *
     * Scenario: Prices in catalog already include tax, but need to show
     * both excluding and including tax versions
     */
    public function testCrossBorderTaxScenario(): void
    {
        $this->configureTax([
            'display_type' => TaxConfig::DISPLAY_TYPE_BOTH,
            'tax_rate' => 20.0,
            'price_includes_tax' => true,
        ]);

        $product = $this->loadProductOrSkip('24-MB01');
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test tax calculation disabled
     *
     * When tax calculation is disabled globally, prices should be
     * identical to default pricing (no tax applied)
     */
    public function testTaxCalculationDisabled(): void
    {
        $this->configureTax([
            'display_type' => TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX,
            'tax_rate' => 10.0,
            'tax_enabled' => false,
        ]);

        $product = $this->loadProductOrSkip('24-MB01');
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Assert pricing matches for an existing product instance
     */
    protected function assertPricingMatchesForProduct(ProductInterface $product): void
    {
        $subProducts = $this->getSubProducts($product);

        // Calculate with V1
        $v1Result = $this->v1->addPriceDataByProductType([], $product, $subProducts);

        // Calculate with V2
        $v2Result = $this->v2->addPriceDataByProductType([], $product, $subProducts);

        // Compare results
        $this->assertPricingDataEquals($v1Result, $v2Result, $product);
    }

    /**
     * Create a simple product with special price
     */
    protected function createSimpleProductWithSpecialPrice(
        string $sku,
        float $regularPrice,
        float $specialPrice
    ): ProductInterface {
        $objectManager = Bootstrap::getObjectManager();

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $objectManager->create(\Magento\Catalog\Model\Product::class);
        $product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Test Product with Special Price')
            ->setSku($sku)
            ->setPrice($regularPrice)
            ->setSpecialPrice($specialPrice)
            ->setTaxClassId(2) // Taxable Goods
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_in_stock' => 1]);

        return $this->productRepository->save($product);
    }

    /**
     * Create a simple product with tier prices
     */
    protected function createSimpleProductWithTierPrices(
        string $sku,
        float $regularPrice,
        array $tierPrices
    ): ProductInterface {
        $objectManager = Bootstrap::getObjectManager();

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $objectManager->create(\Magento\Catalog\Model\Product::class);
        $product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Test Product with Tier Price')
            ->setSku($sku)
            ->setPrice($regularPrice)
            ->setTaxClassId(2) // Taxable Goods
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_in_stock' => 1]);

        $savedProduct = $this->productRepository->save($product);

        // Add tier prices
        $tierPriceFactory = $objectManager->get(\Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory::class);
        $tierPriceObjects = [];

        foreach ($tierPrices as $tierPriceData) {
            $tierPrice = $tierPriceFactory->create();
            $tierPrice->setCustomerGroupId($tierPriceData['group']);
            $tierPrice->setQty($tierPriceData['qty']);
            $tierPrice->setValue($tierPriceData['price']);
            $tierPriceObjects[] = $tierPrice;
        }

        $savedProduct->setTierPrices($tierPriceObjects);
        $this->productRepository->save($savedProduct);

        return $this->productRepository->get($sku, false, null, true);
    }

    /**
     * Create a simple product with FPT (Fixed Product Tax / Weee)
     */
    protected function createSimpleProductWithFpt(
        string $sku,
        float $regularPrice,
        float $fptAmount
    ): ProductInterface {
        $objectManager = Bootstrap::getObjectManager();

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $objectManager->create(\Magento\Catalog\Model\Product::class);
        $product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Test Product with FPT')
            ->setSku($sku)
            ->setPrice($regularPrice)
            ->setTaxClassId(2) // Taxable Goods
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_in_stock' => 1]);

        // Set FPT attribute (Weee)
        // Note: This requires the Weee module to be enabled and FPT attribute to exist
        $product->setData('weee', [
            [
                'website_id' => 1,
                'country' => 'US',
                'state' => 0,
                'value' => $fptAmount,
            ],
        ]);

        return $this->productRepository->save($product);
    }

    /**
     * Configure tax settings
     *
     * @param array $config Tax configuration options
     */
    protected function configureTax(array $config): void
    {
        $displayType = $config['display_type'] ?? TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX;
        $taxRate = $config['tax_rate'] ?? 0.0;
        $priceIncludesTax = $config['price_includes_tax'] ?? false;
        $taxEnabled = $config['tax_enabled'] ?? true;

        // Set tax display mode
        $this->mutableConfig->setValue(
            TaxConfig::CONFIG_XML_PATH_PRICE_DISPLAY_TYPE,
            $displayType,
            ScopeInterface::SCOPE_STORE
        );

        // Set whether catalog prices include tax
        $this->mutableConfig->setValue(
            TaxConfig::CONFIG_XML_PATH_PRICE_INCLUDES_TAX,
            $priceIncludesTax ? 1 : 0,
            ScopeInterface::SCOPE_STORE
        );

        // Enable/disable tax calculation
        $this->mutableConfig->setValue(
            'tax/calculation/based_on',
            'shipping',
            ScopeInterface::SCOPE_STORE
        );

        // Create tax rate and rule if tax rate is specified
        if ($taxRate > 0 && $taxEnabled) {
            $this->createTaxRateAndRule($taxRate);
        }
    }

    /**
     * Create tax rate and rule programmatically
     *
     * @param float $taxRate Tax rate percentage (e.g., 10.0 for 10%)
     */
    protected function createTaxRateAndRule(float $taxRate): void
    {
        $objectManager = Bootstrap::getObjectManager();

        // Create tax rate
        /** @var TaxRateInterface $rate */
        $rate = $objectManager->create(TaxRateInterface::class);
        $rate->setCode('Test Tax Rate ' . $taxRate . '%')
            ->setTaxCountryId('US')
            ->setTaxRegionId(0)
            ->setTaxPostcode('*')
            ->setRate($taxRate);

        $savedRate = $this->taxRateRepository->save($rate);
        $this->createdTaxRateIds[] = (int) $savedRate->getId();

        // Get default product tax class (usually ID 2 "Taxable Goods")
        $productTaxClassId = 2;

        // Get default customer tax class (usually ID 3 "Retail Customer")
        $customerTaxClassId = 3;

        // Create tax rule linking rate to product/customer tax classes
        /** @var TaxRuleInterface $rule */
        $rule = $objectManager->create(TaxRuleInterface::class);
        $rule->setCode('Test Tax Rule ' . $taxRate . '%')
            ->setPriority(1)
            ->setPosition(1)
            ->setTaxRateIds([$savedRate->getId()])
            ->setCustomerTaxClassIds([$customerTaxClassId])
            ->setProductTaxClassIds([$productTaxClassId]);

        $savedRule = $this->taxRuleRepository->save($rule);
        $this->createdTaxRuleIds[] = (int) $savedRule->getId();
    }

    /**
     * Configure FPT (Fixed Product Tax / Weee) settings
     *
     * @param array $config FPT configuration options
     */
    protected function configureFpt(array $config): void
    {
        $enabled = $config['enabled'] ?? false;

        // Enable/disable FPT
        $this->mutableConfig->setValue(
            'tax/weee/enable',
            $enabled ? 1 : 0,
            ScopeInterface::SCOPE_STORE
        );

        // Set FPT display mode (include in price, display separately, etc.)
        $this->mutableConfig->setValue(
            'tax/weee/display',
            1, // Include FPT in price
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Save original tax configuration to restore later
     */
    protected function saveOriginalTaxConfig(): void
    {
        $this->originalConfigValues = [
            TaxConfig::CONFIG_XML_PATH_PRICE_DISPLAY_TYPE => $this->scopeConfig->getValue(
                TaxConfig::CONFIG_XML_PATH_PRICE_DISPLAY_TYPE,
                ScopeInterface::SCOPE_STORE
            ),
            TaxConfig::CONFIG_XML_PATH_PRICE_INCLUDES_TAX => $this->scopeConfig->getValue(
                TaxConfig::CONFIG_XML_PATH_PRICE_INCLUDES_TAX,
                ScopeInterface::SCOPE_STORE
            ),
            'tax/weee/enable' => $this->scopeConfig->getValue(
                'tax/weee/enable',
                ScopeInterface::SCOPE_STORE
            ),
            'tax/weee/display' => $this->scopeConfig->getValue(
                'tax/weee/display',
                ScopeInterface::SCOPE_STORE
            ),
        ];
    }

    /**
     * Restore original tax configuration
     */
    protected function restoreOriginalTaxConfig(): void
    {
        foreach ($this->originalConfigValues as $path => $value) {
            $this->mutableConfig->setValue($path, $value, ScopeInterface::SCOPE_STORE);
        }
    }
}
