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
     * Test configurable product with special price
     *
     * Uses sample data configurable product, sets special price on parent
     */
    public function testConfigurableProductWithSpecialPrice(): void
    {
        $product = $this->loadProductOrSkip('MH01');
        $this->setSpecialPrice($product, 49.99);
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test bundle product with special price
     *
     * Uses sample data bundle product with special price applied
     */
    public function testBundleProductWithSpecialPrice(): void
    {
        $product = $this->loadProductOrSkip('24-WG080');
        $this->setSpecialPrice($product, 35.00);
        $this->assertPricingMatchesForProduct($product);
    }

    /**
     * Test grouped product with special price
     *
     * Grouped products don't have their own price, but we test that
     * V1 and V2 handle this edge case identically
     */
    public function testGroupedProductWithSpecialPrice(): void
    {
        $product = $this->loadProductOrSkip('24-WG085');
        // Grouped products don't have special prices on parent, but test the behavior
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
