<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Product;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @magentoDbIsolation disabled
 *
 * @magentoAppIsolation enabled
 */
class PricingTest extends ProductsIndexingTestCase
{
    /** @var int */
    protected const PRODUCT_ID_SIMPLE_STANDARD_PRICE = 1;
    protected const PRODUCT_ID_CONFIGURABLE_STANDARD_PRICE = 62;

    protected const PRODUCT_ID_CONFIGURABLE_CATALOG_PRICE_RULE = 1903;

    protected const PRODUCT_ID_SPECIAL_PRICE = 9;

    /** @var array<int, float> */
    protected const ASSERT_PRODUCT_PRICES = [
        self::PRODUCT_ID_SIMPLE_STANDARD_PRICE           => 34,
        self::PRODUCT_ID_CONFIGURABLE_STANDARD_PRICE     => 52,
        self::PRODUCT_ID_CONFIGURABLE_CATALOG_PRICE_RULE => 39.2,
    ];

    protected ProductRepositoryInterface $productRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);

        $this->indexerRegistry->get('catalogrule_product')->reindexAll();
        $this->indexerRegistry->get('catalogrule_rule')->reindexAll();

        $this->indexSuffix = 'products';
    }

    /**
     * @param int|int[] $productIds
     *
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     */
    protected function indexProducts(int|array $productIds): void
    {
        if (!is_array($productIds)) {
            $productIds = [$productIds];
        }
        $this->productBatchQueueProcessor->processBatch(1, $productIds);
        $this->algoliaConnector->waitLastTask();
    }

    protected function getAlgoliaObjectById(int $productId): ?array
    {
        $indexOptions = $this->getIndexOptions($this->indexSuffix);
        $res = $this->algoliaConnector->getObjects(
            $indexOptions,
            [(string) $productId]
        );

        return reset($res['results']);
    }

    protected function assertAlgoliaPrice(int $productId): void
    {
        $algoliaProduct = $this->getAlgoliaObjectById($productId);
        $this->assertNotNull($algoliaProduct, 'Algolia product index was not successful.');
        $this->assertEquals(self::ASSERT_PRODUCT_PRICES[$productId], $algoliaProduct['price']['USD']['default']);
    }

    /**
     * @depends testMagentoProductData
     *
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
     *
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
     *
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
        $product = $this->objectManager->get(\Magento\Catalog\Model\ProductRepository::class)->getById($productId);
        $this->assertTrue($product->isInStock(), 'Product is not in stock');
        $this->assertTrue($product->getIsSalable(), 'Product is not salable');
        $actualPrice = $product->getFinalPrice();
        $this->assertEquals($actualPrice, $expectedPrice, 'Product price does not match expectation');
    }

    public static function productProvider(): array
    {
        return array_map(
            fn($key, $value) => [$key, $value],
            array_keys(self::ASSERT_PRODUCT_PRICES),
            self::ASSERT_PRODUCT_PRICES
        );
    }

    public function testSpecialPrice(): void
    {
        $date = new \DateTimeImmutable();
        // For special price to apply, it must be within current date range
        $specialPriceTestData = [
            'special_price'     => 29.00,
            'special_from_date' => $date->modify('-2 day')->getTimestamp(),
            'special_to_date'   => $date->modify('+2 day')->getTimestamp()
        ];
        $regularPrice = 32.00;

        $indexOptions = $this->getIndexOptions('products');

        // First index with default
        $this->productBatchQueueProcessor->processBatch(1, [self::PRODUCT_ID_SPECIAL_PRICE]);
        $this->algoliaConnector->waitLastTask();

        $res = $this->algoliaConnector->getObjects(
            $indexOptions,
            [(string) self::PRODUCT_ID_SPECIAL_PRICE]
        );
        $algoliaProduct = reset($res['results']);

        if (!$algoliaProduct || !array_key_exists('price', $algoliaProduct)) {
            $this->markTestIncomplete('Hit was not returned correctly from Algolia. No Hit to run assertions.');
        }

        $this->assertEquals($regularPrice, $algoliaProduct['price']['USD']['default']);
        $this->assertNotEquals(
            $specialPriceTestData['special_from_date'],
            $algoliaProduct['price']['USD']['special_from_date']
        );
        $this->assertNotEquals(
            $specialPriceTestData['special_to_date'],
            $algoliaProduct['price']['USD']['special_to_date']
        );

        // Modify with test data
        $product = $this->productRepository->getById(self::PRODUCT_ID_SPECIAL_PRICE);
        $originalAttributes = [
            'special_price' => $product->getData('special_price'),
            'special_from_date' => $product->getData('special_from_date'),
            'special_to_date' => $product->getData('special_to_date'),
        ];

        try {
            $product->setCustomAttributes($specialPriceTestData);
            $this->productRepository->save($product);

            $this->productBatchQueueProcessor->processBatch(1, [self::PRODUCT_ID_SPECIAL_PRICE]);
            $this->algoliaConnector->waitLastTask();

            $res = $this->algoliaConnector->getObjects(
                $indexOptions,
                [(string) self::PRODUCT_ID_SPECIAL_PRICE]
            );
            $algoliaProduct = reset($res['results']);

            $this->assertEquals($specialPriceTestData['special_price'], $algoliaProduct['price']['USD']['default']);
            $this->assertEquals('$32.00', $algoliaProduct['price']['USD']['default_original_formated']);
            $this->assertEquals(
                $specialPriceTestData['special_from_date'],
                $algoliaProduct['price']['USD']['special_from_date']
            );
            $this->assertEquals(
                $specialPriceTestData['special_to_date'],
                $algoliaProduct['price']['USD']['special_to_date']
            );
        } finally {
            $product = $this->productRepository->getById(self::PRODUCT_ID_SPECIAL_PRICE, false, null, true);
            $product->setCustomAttributes($originalAttributes);
            $this->productRepository->save($product);
        }
    }

}
