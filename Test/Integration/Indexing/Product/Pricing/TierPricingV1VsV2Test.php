<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\Pricing;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductTierPriceInterface;
use Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Integration tests comparing Pricing V1 vs V2 with TIER PRICES
 *
 * Tests tier price (quantity-based pricing) behavior with:
 * - Quantity breaks (buy 10+, buy 50+, buy 100+)
 * - Per-customer-group tier prices
 * - "All Groups" tier prices
 * - Tier prices combined with special prices
 * - Tier prices on complex products (configurable, bundle, grouped)
 *
 * Validates that V2 produces identical results to V1 for:
 * - Tier price calculation and selection (lowest applicable tier)
 * - Customer group-specific tier pricing
 * - Interaction with special prices (lowest wins)
 * - Multiple currencies with tier prices
 *
 * Note: Creates test products programmatically with tier prices configured.
 * Products are automatically cleaned up by the test framework's transaction rollback.
 *
 * @group algolia_pricing
 * @group algolia_pricing_comparison
 * @group algolia_pricing_tier
 */
class TierPricingV1VsV2Test extends AbstractPricingV1VsV2TestCase
{
    private ?ProductTierPriceInterfaceFactory $tierPriceFactory = null;

    protected function setUp(): void
    {
        parent::setUp();

        $objectManager = Bootstrap::getObjectManager();
        $this->tierPriceFactory = $objectManager->get(ProductTierPriceInterfaceFactory::class);
    }

    /**
     * Test simple product with basic tier pricing
     *
     * Quantity breaks: 1 unit = $100, 10+ = $90, 50+ = $80, 100+ = $75
     */
    public function testSimpleProductWithTierPrices(): void
    {
        $product = $this->createSimpleProductWithTierPrices(
            'test-simple-tier-price',
            100.00,
            [
                ['qty' => 10, 'price' => 90.00, 'group' => GroupInterface::CUST_GROUP_ALL],
                ['qty' => 50, 'price' => 80.00, 'group' => GroupInterface::CUST_GROUP_ALL],
                ['qty' => 100, 'price' => 75.00, 'group' => GroupInterface::CUST_GROUP_ALL],
            ]
        );

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test simple product with customer group-specific tier prices
     *
     * General: 10+ = $90
     * Wholesale (ID 1): 10+ = $85
     * Retailer (ID 2): 10+ = $87
     */
    public function testSimpleProductWithGroupTierPrices(): void
    {
        $product = $this->createSimpleProductWithTierPrices(
            'test-simple-group-tier-price',
            100.00,
            [
                // General group (All Groups)
                ['qty' => 10, 'price' => 90.00, 'group' => GroupInterface::CUST_GROUP_ALL],
                // Wholesale group gets better price
                ['qty' => 10, 'price' => 85.00, 'group' => 1],
                // Retailer group gets intermediate price
                ['qty' => 10, 'price' => 87.00, 'group' => 2],
            ]
        );

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test tier price combined with special price
     *
     * Base: $100, Special: $95, Tier (10+): $90
     * Expected: Tier price wins (lowest)
     */
    public function testTierPriceCombinedWithSpecialPrice(): void
    {
        $product = $this->createSimpleProductWithTierPrices(
            'test-tier-with-special',
            100.00,
            [
                ['qty' => 10, 'price' => 90.00, 'group' => GroupInterface::CUST_GROUP_ALL],
            ]
        );

        // Add special price that's higher than tier price
        $product->setSpecialPrice(95.00);
        $this->productRepository->save($product);

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test tier price that's higher than special price
     *
     * Base: $100, Special: $80, Tier (10+): $90
     * Expected: Special price wins (lowest)
     */
    public function testSpecialPriceWinsOverTierPrice(): void
    {
        $product = $this->createSimpleProductWithTierPrices(
            'test-special-wins-tier',
            100.00,
            [
                ['qty' => 10, 'price' => 90.00, 'group' => GroupInterface::CUST_GROUP_ALL],
            ]
        );

        // Add special price that's lower than tier price
        $product->setSpecialPrice(80.00);
        $this->productRepository->save($product);

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test configurable product with tier prices on child variants
     *
     * Applies tier pricing to configurable child products to test
     * that V1 and V2 correctly aggregate child tier pricing
     */
    public function testConfigurableProductWithTierPrices(): void
    {
        $product = $this->loadProductOrSkip('MH01');
        $subProducts = $this->getSubProducts($product);

        if (empty($subProducts)) {
            $this->markTestSkipped('Configurable product MH01 has no child products');
        }

        // Apply tier prices to first child variant
        $firstChild = reset($subProducts);
        $childPrice = (float) $firstChild->getPrice();
        $this->addTierPricesToProduct($firstChild, [
            ['qty' => 10, 'price' => $childPrice * 0.9, 'group' => GroupInterface::CUST_GROUP_ALL],
            ['qty' => 50, 'price' => $childPrice * 0.85, 'group' => GroupInterface::CUST_GROUP_ALL],
        ]);

        // Apply tier prices to second child if available
        if (count($subProducts) > 1) {
            $secondChild = $subProducts[1];
            $secondChildPrice = (float) $secondChild->getPrice();
            $this->addTierPricesToProduct($secondChild, [
                ['qty' => 10, 'price' => $secondChildPrice * 0.88, 'group' => GroupInterface::CUST_GROUP_ALL],
            ]);
        }

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test bundle product with tier prices on bundle selections
     *
     * Applies tier pricing to bundle selection products to test
     * that V1 and V2 correctly calculate bundle pricing with tier prices
     */
    public function testBundleProductWithTierPrices(): void
    {
        $product = $this->loadProductOrSkip('24-WG080');
        $subProducts = $this->getSubProducts($product);

        if (empty($subProducts)) {
            $this->markTestSkipped('Bundle product 24-WG080 has no selections');
        }

        // Apply tier prices to first bundle selection
        $firstSelection = reset($subProducts);
        $selectionPrice = (float) $firstSelection->getPrice();
        $this->addTierPricesToProduct($firstSelection, [
            ['qty' => 5, 'price' => $selectionPrice * 0.92, 'group' => GroupInterface::CUST_GROUP_ALL],
        ]);

        // Apply tier prices to second selection if available
        if (count($subProducts) > 1) {
            $secondSelection = $subProducts[1];
            $secondSelectionPrice = (float) $secondSelection->getPrice();
            $this->addTierPricesToProduct($secondSelection, [
                ['qty' => 5, 'price' => $secondSelectionPrice * 0.95, 'group' => GroupInterface::CUST_GROUP_ALL],
            ]);
        }

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test grouped product with tier prices on associated products
     *
     * Applies tier pricing to associated products within the group to test
     * that V1 and V2 correctly display the lowest tier price
     */
    public function testGroupedProductWithTierPrices(): void
    {
        $product = $this->loadProductOrSkip('24-WG085_Group');
        $subProducts = $this->getSubProducts($product);

        if (empty($subProducts)) {
            $this->markTestSkipped('Grouped product 24-WG085_Group has no associated products');
        }

        // Apply tier prices to first associated product
        $firstAssociated = reset($subProducts);
        $associatedPrice = (float) $firstAssociated->getPrice();
        $this->addTierPricesToProduct($firstAssociated, [
            ['qty' => 10, 'price' => $associatedPrice * 0.85, 'group' => GroupInterface::CUST_GROUP_ALL],
            ['qty' => 50, 'price' => $associatedPrice * 0.80, 'group' => GroupInterface::CUST_GROUP_ALL],
        ]);

        // Apply tier prices to second associated product if available
        if (count($subProducts) > 1) {
            $secondAssociated = $subProducts[1];
            $secondAssociatedPrice = (float) $secondAssociated->getPrice();
            $this->addTierPricesToProduct($secondAssociated, [
                ['qty' => 10, 'price' => $secondAssociatedPrice * 0.90, 'group' => GroupInterface::CUST_GROUP_ALL],
            ]);
        }

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test multiple tier prices selecting the lowest applicable
     *
     * Multiple tiers: 10+ = $90, 20+ = $85, 50+ = $80
     * Expected: Lowest tier (50+: $80) should be used for tier_price field
     */
    public function testMultipleTierPricesSelectsLowest(): void
    {
        $product = $this->createSimpleProductWithTierPrices(
            'test-multiple-tiers',
            100.00,
            [
                ['qty' => 10, 'price' => 90.00, 'group' => GroupInterface::CUST_GROUP_ALL],
                ['qty' => 20, 'price' => 85.00, 'group' => GroupInterface::CUST_GROUP_ALL],
                ['qty' => 50, 'price' => 80.00, 'group' => GroupInterface::CUST_GROUP_ALL],
                ['qty' => 100, 'price' => 75.00, 'group' => GroupInterface::CUST_GROUP_ALL],
            ]
        );

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test group-specific tier price overrides "All Groups" tier price
     *
     * All Groups: 10+ = $90
     * Group 1: 10+ = $85
     * Expected for Group 1: $85 (group-specific wins)
     */
    public function testGroupSpecificTierOverridesAllGroups(): void
    {
        $product = $this->createSimpleProductWithTierPrices(
            'test-group-override-tier',
            100.00,
            [
                // All Groups tier price
                ['qty' => 10, 'price' => 90.00, 'group' => GroupInterface::CUST_GROUP_ALL],
                // Group 1 gets better price
                ['qty' => 10, 'price' => 85.00, 'group' => 1],
            ]
        );

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
     * Create a simple product with tier prices for testing
     *
     * @param string $sku Product SKU
     * @param float $regularPrice Base price
     * @param array $tierPrices Array of tier prices: [['qty' => 10, 'price' => 90.00, 'group' => 0], ...]
     */
    protected function createSimpleProductWithTierPrices(
        string $sku,
        float $regularPrice,
        array $tierPrices
    ): ProductInterface {
        $objectManager = Bootstrap::getObjectManager();

        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $objectManager->create(\Magento\Catalog\Model\Product::class);
        $product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
            ->setAttributeSetId(4) // Default attribute set
            ->setWebsiteIds([1])
            ->setName('Test Product with Tier Price')
            ->setSku($sku)
            ->setPrice($regularPrice)
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_in_stock' => 1]);

        $savedProduct = $productRepository->save($product);

        // Add tier prices
        $this->addTierPricesToProduct($savedProduct, $tierPrices);

        // Reload to get tier prices
        return $productRepository->get($sku, false, null, true);
    }

    /**
     * Add tier prices to an existing product
     *
     * @param ProductInterface $product Product to add tier prices to
     * @param array $tierPrices Array of tier prices: [['qty' => 10, 'price' => 90.00, 'group' => 0], ...]
     */
    protected function addTierPricesToProduct(ProductInterface $product, array $tierPrices): void
    {
        $tierPriceObjects = [];

        foreach ($tierPrices as $tierPriceData) {
            /** @var ProductTierPriceInterface $tierPrice */
            $tierPrice = $this->tierPriceFactory->create();
            $tierPrice->setCustomerGroupId($tierPriceData['group']);
            $tierPrice->setQty($tierPriceData['qty']);
            $tierPrice->setValue($tierPriceData['price']);

            $tierPriceObjects[] = $tierPrice;
        }

        $product->setTierPrices($tierPriceObjects);
        $this->productRepository->save($product);
    }
}
