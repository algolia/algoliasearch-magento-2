<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\Pricing;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Integration tests comparing Pricing V1 vs V2 with SPECIAL PRICES
 *
 * Tests special price (sale price) behavior with:
 * - Active special prices (within date range)
 * - Time-limited special prices
 * - Special prices combined with regular prices
 * - Original price preservation
 *
 * Validates that V2 produces identical results to V1 for:
 * - Products with active special prices
 * - All product types with discounts
 * - Original price vs sale price tracking
 * - Multiple currencies with special prices
 *
 * Note: Tests create products with special prices programmatically since sample data
 * may not have special prices configured or active at test runtime. Created products
 * are automatically cleaned up by the test framework's transaction rollback.
 *
 * @group algolia_pricing
 * @group algolia_pricing_comparison
 * @group algolia_pricing_special
 */
class SpecialPricePricingV1VsV2Test extends AbstractPricingV1VsV2TestCase
{
    /**
     * Test simple product with active special price
     *
     * Creates a simple product with special price lower than regular price
     */
    public function testSimpleProductWithSpecialPrice(): void
    {
        $product = $this->createSimpleProductWithSpecialPrice('test-simple-special-price', 100.00, 79.99);
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test configurable product with special price on child products
     *
     * Applies special prices to configurable child products (variants)
     * to test that V1 and V2 correctly aggregate child special pricing
     */
    public function testConfigurableProductWithSpecialPrice(): void
    {
        $product = $this->loadProductOrSkip('MH01');
        $subProducts = $this->getSubProducts($product);

        if (empty($subProducts)) {
            $this->markTestSkipped('Configurable product MH01 has no child products');
        }

        // Apply special price to first child variant
        $firstChild = reset($subProducts);
        $childPrice = (float) $firstChild->getPrice();
        $this->setSpecialPrice($firstChild, $childPrice * 0.8); // 20% discount

        // If there are multiple children, apply different special price to second child
        if (count($subProducts) > 1) {
            $secondChild = $subProducts[1];
            $secondChildPrice = (float) $secondChild->getPrice();
            $this->setSpecialPrice($secondChild, $secondChildPrice * 0.85); // 15% discount
        }

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test bundle product with special price on bundle selections
     *
     * Applies special prices to bundle selection products (bundled items)
     * to test that V1 and V2 correctly calculate bundle pricing with child specials
     */
    public function testBundleProductWithSpecialPrice(): void
    {
        $product = $this->loadProductOrSkip('24-WG080');
        $subProducts = $this->getSubProducts($product);

        if (empty($subProducts)) {
            $this->markTestSkipped('Bundle product 24-WG080 has no selections');
        }

        // Apply special price to first bundle selection
        $firstSelection = reset($subProducts);
        $selectionPrice = (float) $firstSelection->getPrice();
        $this->setSpecialPrice($firstSelection, $selectionPrice * 0.75); // 25% discount

        // Apply special price to another selection if available
        if (count($subProducts) > 1) {
            $secondSelection = $subProducts[1];
            $secondSelectionPrice = (float) $secondSelection->getPrice();
            $this->setSpecialPrice($secondSelection, $secondSelectionPrice * 0.9); // 10% discount
        }

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test grouped product with special price on associated products
     *
     * Applies special prices to associated products within the group
     * to test that V1 and V2 correctly display the lowest price from associated products
     */
    public function testGroupedProductWithSpecialPrice(): void
    {
        $product = $this->loadProductOrSkip('24-WG085_Group');
        $subProducts = $this->getSubProducts($product);

        if (empty($subProducts)) {
            $this->markTestSkipped('Grouped product 24-WG085_Group has no associated products');
        }

        // Apply special price to first associated product
        $firstAssociated = reset($subProducts);
        $associatedPrice = (float) $firstAssociated->getPrice();
        $this->setSpecialPrice($firstAssociated, $associatedPrice * 0.7); // 30% discount

        // Apply different special price to second associated product if available
        if (count($subProducts) > 1) {
            $secondAssociated = $subProducts[1];
            $secondAssociatedPrice = (float) $secondAssociated->getPrice();
            $this->setSpecialPrice($secondAssociated, $secondAssociatedPrice * 0.85); // 15% discount
        }

        // Apply special price to third associated product if available
        if (count($subProducts) > 2) {
            $thirdAssociated = $subProducts[2];
            $thirdAssociatedPrice = (float) $thirdAssociated->getPrice();
            $this->setSpecialPrice($thirdAssociated, $thirdAssociatedPrice * 0.9); // 10% discount
        }

        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test simple product with expired special price
     *
     * Special price outside valid date range should be ignored
     */
    public function testSimpleProductWithExpiredSpecialPrice(): void
    {
        $product = $this->createSimpleProductWithSpecialPrice(
            'test-simple-expired-special',
            100.00,
            79.99,
            '2020-01-01', // Past date
            '2020-12-31'  // Past date
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
     * Create a simple product with special price for testing
     */
    protected function createSimpleProductWithSpecialPrice(
        string $sku,
        float $regularPrice,
        float $specialPrice,
        ?string $specialFromDate = null,
        ?string $specialToDate = null
    ): ProductInterface {
        $objectManager = Bootstrap::getObjectManager();

        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepository */
        $productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $objectManager->create(\Magento\Catalog\Model\Product::class);
        $product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
            ->setAttributeSetId(4) // Default attribute set
            ->setWebsiteIds([1])
            ->setName('Test Product with Special Price')
            ->setSku($sku)
            ->setPrice($regularPrice)
            ->setSpecialPrice($specialPrice)
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_in_stock' => 1]);

        if ($specialFromDate !== null) {
            $product->setSpecialFromDate($specialFromDate);
        }
        if ($specialToDate !== null) {
            $product->setSpecialToDate($specialToDate);
        }

        return $productRepository->save($product);
    }

    /**
     * Set special price on an existing product
     */
    protected function setSpecialPrice(
        ProductInterface $product,
        float $specialPrice,
        ?string $specialFromDate = null,
        ?string $specialToDate = null
    ): ProductInterface {
        $product->setSpecialPrice($specialPrice);

        if ($specialFromDate !== null) {
            $product->setSpecialFromDate($specialFromDate);
        }
        if ($specialToDate !== null) {
            $product->setSpecialToDate($specialToDate);
        }

        return $this->productRepository->save($product);
    }

}
