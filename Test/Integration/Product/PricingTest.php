<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Model\IndexOptions;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class PricingTest extends ProductsIndexingTestCase
{
    /**
     * @var int
     */
    protected const PRODUCT_ID_SIMPLE_STANDARD_PRICE = 1;
    protected const PRODUCT_ID_CONFIGURABLE_STANDARD_PRICE = 62;

    protected const PRODUCT_ID_CONFIGURABLE_CATALOG_PRICE_RULE = 1903;

    const SPECIAL_PRICE_TEST_PRODUCT_ID = 9;

    /**
     * @var array<int, float>
     */
    protected const ASSERT_PRODUCT_PRICES = [
        self::PRODUCT_ID_SIMPLE_STANDARD_PRICE           => 34,
        self::PRODUCT_ID_CONFIGURABLE_STANDARD_PRICE     => 52,
        self::PRODUCT_ID_CONFIGURABLE_CATALOG_PRICE_RULE => 39.2
    ];

    protected ?string $indexName = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->indexSuffix = 'products';
        $this->indexName = $this->getIndexName('default');
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
        $indexOptions = new IndexOptions([
            IndexOptionsInterface::ENFORCED_INDEX_NAME => $this->indexName
        ]);

        $res = $this->algoliaHelper->getObjects(
            $indexOptions,
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
     * @depends testMagentoProductData
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     */
    public function testCatalogPriceRule(): void
    {
        $productId = self::PRODUCT_ID_CONFIGURABLE_CATALOG_PRICE_RULE;
        $this->indexProducts($productId);
        $this->assertAlgoliaPrice($productId);
    }

    /**
     * @dataProvider productProvider
     */
    public function testMagentoProductData(int $productId, float $expectedPrice): void
    {
        /**
         * @var Product $product
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

    public function testSpecialPrice(): void
    {
        $this->productIndexer->execute([self::SPECIAL_PRICE_TEST_PRODUCT_ID]);
        $this->algoliaHelper->waitLastTask();

        $indexOptions = new IndexOptions([
            IndexOptionsInterface::ENFORCED_INDEX_NAME => $this->indexPrefix . 'default_products',
        ]);

        $res = $this->algoliaHelper->getObjects(
            $indexOptions,
            [(string) self::SPECIAL_PRICE_TEST_PRODUCT_ID]
        );
        $algoliaProduct = reset($res['results']);

        if (!$algoliaProduct || !array_key_exists('price', $algoliaProduct)) {
            $this->markTestIncomplete('Hit was not returned correctly from Algolia. No Hit to run assetions.');
        }

        $this->assertEquals(32, $algoliaProduct['price']['USD']['default']);
        $this->assertEquals('', $algoliaProduct['price']['USD']['special_from_date']);
        $this->assertEquals('', $algoliaProduct['price']['USD']['special_to_date']);

        $specialPrice = 29;
        $fromDatetime = new \DateTime();
        $toDatetime = new \DateTime();
        $priceFrom = $fromDatetime->modify('-2 day')->format('Y-m-d H:i:s');
        $priceTo = $toDatetime->modify('+2 day')->format('Y-m-d H:i:s');

        $product = $this->objectManager->create(Product::class);
        $product->load(self::SPECIAL_PRICE_TEST_PRODUCT_ID);

        $product->setCustomAttributes([
            'special_price' => $specialPrice,
            'special_from_date' => date($priceFrom),
            'special_to_date' => date($priceTo),
        ]);
        $product->save();

        $this->productIndexer->execute([self::SPECIAL_PRICE_TEST_PRODUCT_ID]);
        $this->algoliaHelper->waitLastTask();

        $indexOptions = new IndexOptions([
            IndexOptionsInterface::ENFORCED_INDEX_NAME => $this->indexPrefix . 'default_products',
        ]);

        $res = $this->algoliaHelper->getObjects(
            $indexOptions,
            [(string) self::SPECIAL_PRICE_TEST_PRODUCT_ID]
        );
        $algoliaProduct = reset($res['results']);

        $this->assertEquals($specialPrice, $algoliaProduct['price']['USD']['default']);
        $this->assertEquals("$32.00", $algoliaProduct['price']['USD']['default_original_formated']);
    }

    protected function tearDown(): void
    {
        /** @var Product $product */
        $product = $this->objectManager->create(Product::class);
        $product->load(self::SPECIAL_PRICE_TEST_PRODUCT_ID);

        $product->setCustomAttributes([
            'special_price' => null,
            'special_from_date' => null,
            'special_to_date' => null,
        ]);
        $product->getResource()->saveAttribute($product, 'special_price');
        $product->save();

        parent::tearDown();
    }

}
