<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Model\Indexer\Product as ProductIndexer;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;
use function PHPUnit\Framework\assertTrue;

/**
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class PricingTest extends TestCase
{

    /**
     * @var int
     */
    protected const PRODUCT_ID_SIMPLE_STANDARD_PRICE = 1;
    protected const PRODUCT_ID_CONFIGURABLE_STANDARD_PRICE = 62;
    /**
     * @var array<int, float>
     */
    protected const ASSERT_PRODUCT_PRICES = [
        self::PRODUCT_ID_SIMPLE_STANDARD_PRICE => 34,
        self::PRODUCT_ID_CONFIGURABLE_STANDARD_PRICE => 52
    ];

    protected ?ProductIndexer $productIndexer = null;
    protected ?string $indexName = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productIndexer = $this->objectManager->get(ProductIndexer::class);
        $this->indexSuffix = 'products';
        $this->indexName = $this->getIndexName('default');

        $this->objectManager
            ->get(IndexerRegistry::class)
            ->get('catalog_product_price')
            ->reindexAll();
    }

    /**
     * @param int|int[] $productIds
     * @return void
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     */
    protected function indexProducts(int|array $productIds): void
    {
        if (!is_array($productIds)) {
            $productIds = [$productIds];
        }
        $this->productIndexer->execute($productIds);
        $this->algoliaHelper->waitLastTask();
    }

    protected function getAlgoliaObjectById(int $productId): ?array
    {
        $res = $this->algoliaHelper->getObjects(
            $this->indexName,
            [(string) $productId]
        );
        return reset($res['results']);
    }

    protected function assertAlgoliaPrice(int $productId): void
    {
        $algoliaProduct = $this->getAlgoliaObjectById($productId);
        $this->assertNotNull($algoliaProduct, "Algolia product index was not successful.");
        $this->assertEquals(self::ASSERT_PRODUCT_PRICES[$productId], $algoliaProduct['price']['USD']['default']);
    }

    /**
     * @depends testMagentoProductData
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     */
    public function testRegularPriceSimple(): void
    {
        $productId = self::PRODUCT_ID_SIMPLE_STANDARD_PRICE;
        $this->indexProducts($productId);
        $this->assertAlgoliaPrice($productId);
    }

    /**
     * @depends testMagentoProductData
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     */
    public function testRegularPriceConfigurable(): void
    {
        $productId = self::PRODUCT_ID_CONFIGURABLE_STANDARD_PRICE;
        $this->indexProducts($productId);
        $this->assertAlgoliaPrice($productId);
    }

    /**
     * @dataProvider productProvider
     */
    public function testMagentoProductData(int $productId, float $expectedPrice): void
    {
        /**
         * @var \Magento\Catalog\Model\Product $product
         */
        $product = $this->objectManager->get('Magento\Catalog\Model\ProductRepository')->getById($productId);
        $this->assertTrue($product->isInStock(), "Product is not in stock");
        $this->assertTrue($product->getIsSalable(), "Product is not salable");
        $actualPrice = $product->getFinalPrice();
        $this->assertEquals($actualPrice, $expectedPrice, "Product price does not match expectation");
    }

    public static function productProvider(): array
    {
        return array_map(
            function ($key, $value) {
                return [$key, $value];
            },
            array_keys(self::ASSERT_PRODUCT_PRICES),
            self::ASSERT_PRODUCT_PRICES
        );
    }

}
