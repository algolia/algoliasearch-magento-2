<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\Pricing;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogRule\Api\CatalogRuleRepositoryInterface;
use Magento\CatalogRule\Model\Rule;
use Magento\CatalogRule\Model\RuleFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Integration tests comparing Pricing V1 vs V2 with CATALOG PRICE RULES
 *
 * Tests catalog rule pricing behavior with:
 * - Percentage discounts (10% off, 25% off)
 * - Fixed amount discounts ($10 off)
 * - Customer group restrictions
 * - Date-based rule validity (active vs expired)
 * - Multiple rules with priority handling
 * - Rule interactions with special prices and tier prices
 * - Rules on complex products (configurable, bundle, grouped)
 *
 * Validates that V2 produces identical results to V1 for:
 * - Catalog rule application and calculation
 * - Rule priority and stop processing logic
 * - Customer group filtering in rules
 * - Date-based rule activation
 * - Interaction with special/tier prices (lowest wins)
 *
 * Note: Creates catalog rules programmatically. Rules are applied via indexer.
 * DB isolation is disabled because the catalog rule indexer performs DDL operations
 * (creating/modifying index tables) which cannot run inside a transaction.
 *
 * @group algolia_pricing
 * @group algolia_pricing_comparison
 * @group algolia_pricing_catalog_rules
 * @magentoDbIsolation disabled
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 */
class CatalogRulePricingV1VsV2Test extends AbstractPricingV1VsV2TestCase
{
    protected const CONFIG_PATH_INDEXING_ENABLED = 'algoliasearch_indexing_manager/algolia_indexing/enable_indexing';

    private ?RuleFactory $ruleFactory = null;
    private ?CatalogRuleRepositoryInterface $ruleRepository = null;
    private ?ResourceConnection $resourceConnection = null;

    /** @var array<int> Rule IDs to clean up */
    private array $createdRuleIds = [];

    /** @var array<string> Product SKUs to clean up */
    private array $createdProductSkus = [];

    protected function setUp(): void
    {
        parent::setUp();

        $objectManager = Bootstrap::getObjectManager();
        $this->ruleFactory = $objectManager->get(RuleFactory::class);
        $this->ruleRepository = $objectManager->get(CatalogRuleRepositoryInterface::class);
        $this->resourceConnection = $objectManager->get(ResourceConnection::class);

        $this->mutableConfig->setValue(self::CONFIG_PATH_INDEXING_ENABLED, 0, ScopeInterface::SCOPE_STORE);
    }

    protected function tearDown(): void
    {
        // Clean up created catalog rules
        foreach ($this->createdRuleIds as $ruleId) {
            try {
                $this->ruleRepository->deleteById($ruleId);
            } catch (\Exception $e) {
                // Ignore cleanup errors - test database will be reset
            }
        }

        // Clean up created products
        foreach ($this->createdProductSkus as $sku) {
            try {
                $this->productRepository->deleteById($sku);
            } catch (\Exception $e) {
                // Ignore cleanup errors - test database will be reset
            }
        }

        $this->createdRuleIds = [];
        $this->createdProductSkus = [];

        $this->mutableConfig->setValue(self::CONFIG_PATH_INDEXING_ENABLED, 1, ScopeInterface::SCOPE_STORE);

        parent::tearDown();
    }

    /**
     * Test simple product with percentage discount catalog rule
     *
     * Rule: 20% off for all customer groups
     * Base price: $100 → Expected: $80
     */
    public function testSimpleProductWithPercentageDiscount(): void
    {
        $product = $this->createSimpleProduct('test-catalog-rule-percent', 100.00);

        // Create 20% off rule
        $rule = $this->createCatalogRule([
            'name' => '20% Off All Products',
            'discount_amount' => 20,
            'simple_action' => 'by_percent',
            'customer_group_ids' => [0, 1, 2, 3], // All groups
        ]);

        $this->applyRules();
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test simple product with fixed amount discount catalog rule
     *
     * Rule: $15 off for all customer groups
     * Base price: $100 → Expected: $85
     */
    public function testSimpleProductWithFixedDiscount(): void
    {
        $product = $this->createSimpleProduct('test-catalog-rule-fixed', 100.00);

        // Create $15 off rule
        $rule = $this->createCatalogRule([
            'name' => '$15 Off All Products',
            'discount_amount' => 15,
            'simple_action' => 'by_fixed',
            'customer_group_ids' => [0, 1, 2, 3],
        ]);

        $this->applyRules();
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test catalog rule with customer group restrictions
     *
     * Rule: 25% off for Wholesale (group 1) only
     * General customers: No discount
     * Wholesale customers: 25% off
     */
    public function testCatalogRuleWithGroupRestrictions(): void
    {
        $product = $this->createSimpleProduct('test-catalog-rule-groups', 100.00);

        // Create rule for Wholesale group only
        $rule = $this->createCatalogRule([
            'name' => '25% Off for Wholesale',
            'discount_amount' => 25,
            'simple_action' => 'by_percent',
            'customer_group_ids' => [1], // Wholesale only
        ]);

        $this->applyRules();
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test expired catalog rule is not applied
     *
     * Rule: 30% off but expired yesterday
     * Expected: No discount applied
     */
    public function testExpiredCatalogRuleNotApplied(): void
    {
        $product = $this->createSimpleProduct('test-catalog-rule-expired', 100.00);

        // Create expired rule
        $rule = $this->createCatalogRule([
            'name' => 'Expired 30% Off',
            'discount_amount' => 30,
            'simple_action' => 'by_percent',
            'customer_group_ids' => [0, 1, 2, 3],
            'from_date' => date('Y-m-d', strtotime('-7 days')),
            'to_date' => date('Y-m-d', strtotime('-1 day')),
        ]);

        $this->applyRules();
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test active catalog rule within date range
     *
     * Rule: 15% off, active today
     * Expected: Discount applied
     */
    public function testActiveCatalogRuleWithinDateRange(): void
    {
        $product = $this->createSimpleProduct('test-catalog-rule-active', 100.00);

        // Create active rule
        $rule = $this->createCatalogRule([
            'name' => 'Active 15% Off',
            'discount_amount' => 15,
            'simple_action' => 'by_percent',
            'customer_group_ids' => [0, 1, 2, 3],
            'from_date' => date('Y-m-d', strtotime('-1 day')),
            'to_date' => date('Y-m-d', strtotime('+7 days')),
        ]);

        $this->applyRules();
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test catalog rule combined with special price
     *
     * Base: $100, Special: $85, Catalog Rule: 20% off (= $80)
     * Expected: Catalog rule wins (lowest at $80)
     */
    public function testCatalogRuleWinsOverSpecialPrice(): void
    {
        $product = $this->createSimpleProduct('test-rule-vs-special', 100.00);
        $product->setSpecialPrice(85.00);
        $this->productRepository->save($product);

        // Catalog rule gives better discount
        $rule = $this->createCatalogRule([
            'name' => '20% Off Beats Special',
            'discount_amount' => 20,
            'simple_action' => 'by_percent',
            'customer_group_ids' => [0, 1, 2, 3],
        ]);

        $this->applyRules();
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test special price wins over catalog rule
     *
     * Base: $100, Special: $70, Catalog Rule: 20% off (= $80)
     * Expected: Special price wins (lowest at $70)
     */
    public function testSpecialPriceWinsOverCatalogRule(): void
    {
        $product = $this->createSimpleProduct('test-special-vs-rule', 100.00);
        $product->setSpecialPrice(70.00);
        $this->productRepository->save($product);

        // Special price is better than catalog rule
        $rule = $this->createCatalogRule([
            'name' => '20% Off Loses to Special',
            'discount_amount' => 20,
            'simple_action' => 'by_percent',
            'customer_group_ids' => [0, 1, 2, 3],
        ]);

        $this->applyRules();
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test multiple catalog rules with priority
     *
     * Rule 1 (Priority 10): 10% off
     * Rule 2 (Priority 20): 25% off
     * Expected: Both rules should be evaluated, best discount wins
     */
    public function testMultipleCatalogRulesWithPriority(): void
    {
        $product = $this->createSimpleProduct('test-multiple-rules', 100.00);

        // Lower priority number = higher priority
        $rule1 = $this->createCatalogRule([
            'name' => 'First Rule 10% Off',
            'discount_amount' => 10,
            'simple_action' => 'by_percent',
            'customer_group_ids' => [0, 1, 2, 3],
            'sort_order' => 10,
        ]);

        $rule2 = $this->createCatalogRule([
            'name' => 'Second Rule 25% Off',
            'discount_amount' => 25,
            'simple_action' => 'by_percent',
            'customer_group_ids' => [0, 1, 2, 3],
            'sort_order' => 20,
        ]);

        $this->applyRules();
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test configurable product with catalog rule
     *
     * Applies catalog rule to see if V1 and V2 handle
     * configurable product rule pricing identically
     */
    public function testConfigurableProductWithCatalogRule(): void
    {
        $product = $this->loadProductOrSkip('MH01');

        $rule = $this->createCatalogRule([
            'name' => '15% Off Configurable',
            'discount_amount' => 15,
            'simple_action' => 'by_percent',
            'customer_group_ids' => [0, 1, 2, 3],
        ]);

        $this->applyRules();
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test bundle product with catalog rule
     *
     * Catalog rule should apply to bundle selections
     */
    public function testBundleProductWithCatalogRule(): void
    {
        $product = $this->loadProductOrSkip('24-WG080');

        $rule = $this->createCatalogRule([
            'name' => '20% Off Bundle',
            'discount_amount' => 20,
            'simple_action' => 'by_percent',
            'customer_group_ids' => [0, 1, 2, 3],
        ]);

        $this->applyRules();
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test grouped product with catalog rule
     *
     * Catalog rule should apply to associated products
     */
    public function testGroupedProductWithCatalogRule(): void
    {
        $product = $this->loadProductOrSkip('24-WG085_Group');

        $rule = $this->createCatalogRule([
            'name' => '10% Off Grouped',
            'discount_amount' => 10,
            'simple_action' => 'by_percent',
            'customer_group_ids' => [0, 1, 2, 3],
        ]);

        $this->applyRules();
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
     * Create a simple product for testing
     */
    protected function createSimpleProduct(string $sku, float $price): ProductInterface
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $objectManager->create(\Magento\Catalog\Model\Product::class);
        $product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Test Product for Catalog Rule')
            ->setSku($sku)
            ->setPrice($price)
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_in_stock' => 1]);

        $savedProduct = $this->productRepository->save($product);
        $this->createdProductSkus[] = $sku;

        return $savedProduct;
    }

    /**
     * Create a catalog price rule
     *
     * @param array $data Rule configuration
     * @return Rule
     */
    protected function createCatalogRule(array $data): Rule
    {
        /** @var Rule $rule */
        $rule = $this->ruleFactory->create();

        $rule->setName($data['name'])
            ->setIsActive(1)
            ->setWebsiteIds('1')
            ->setCustomerGroupIds($data['customer_group_ids'])
            ->setSimpleAction($data['simple_action'] ?? 'by_percent')
            ->setDiscountAmount($data['discount_amount'])
            ->setSortOrder($data['sort_order'] ?? 0)
            ->setStopRulesProcessing($data['stop_rules_processing'] ?? 0);

        // Set date range if provided
        if (isset($data['from_date'])) {
            $rule->setFromDate($data['from_date']);
        }
        if (isset($data['to_date'])) {
            $rule->setToDate($data['to_date']);
        }

        // Apply to all products (no conditions = all products)
        $rule->setConditionsSerialized(json_encode([
            'type' => 'Magento\CatalogRule\Model\Rule\Condition\Combine',
            'attribute' => null,
            'operator' => null,
            'value' => '1',
            'is_value_processed' => null,
            'aggregator' => 'all',
        ]));

        $rule->setActionsSerialized(json_encode([
            'type' => 'Magento\CatalogRule\Model\Rule\Action\Collection',
            'attribute' => null,
            'operator' => '=',
            'value' => null,
        ]));

        $savedRule = $this->ruleRepository->save($rule);
        $this->createdRuleIds[] = (int) $savedRule->getRuleId();

        return $savedRule;
    }

    /**
     * Apply catalog rules by running the indexer
     *
     * Catalog rules require indexing to take effect
     */
    protected function applyRules(): void
    {
        // Run catalog rule indexer to apply all active rules
        $objectManager = Bootstrap::getObjectManager();

        /** @var \Magento\CatalogRule\Model\Indexer\Rule\RuleProductIndexer $indexer */
        $indexer = $objectManager->get(\Magento\CatalogRule\Model\Indexer\Rule\RuleProductIndexer::class);
        $indexer->executeFull();
    }
}
