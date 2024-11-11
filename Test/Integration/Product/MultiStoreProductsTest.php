<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Model\Indexer\Product;
use Algolia\AlgoliaSearch\Test\Integration\MultiStoreTestCase;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Store\Api\WebsiteRepositoryInterface;

/**
 * @magentoDataFixture Algolia_AlgoliaSearch::Test/Integration/_files/second_website_with_two_stores_and_products.php
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class MultiStoreProductsTest extends MultiStoreTestCase
{
    /** @var Product */
    protected $productsIndexer;

    /** @var ProductRepositoryInterface */
    protected $productRepository;

    /**  @var CollectionFactory */
    private $productCollectionFactory;

    /** @var WebsiteRepositoryInterface */
    protected $websiteRepository;

    /*** @var IndexerRegistry */
    protected $indexerRegistry;

    protected $productPriceIndexer;

    const VOYAGE_YOGA_BAG_ID = 8;
    const VOYAGE_YOGA_BAG_NAME = "Voyage Yoga Bag";
    const VOYAGE_YOGA_BAG_NAME_ALT = "Voyage Yoga Bag Alt";

    public const SKUS = [
        '24-MB01',
        '24-MB04',
        '24-MB03',
        '24-MB05',
        '24-MB06',
        '24-WB01'
    ];

    protected function setUp():void
    {
        parent::setUp();

        $this->productsIndexer = $this->objectManager->get(Product::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->productCollectionFactory = $this->objectManager->get(CollectionFactory::class);
        $this->websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);

        $this->indexerRegistry = $this->objectManager->get(IndexerRegistry::class);
        $this->productPriceIndexer = $this->indexerRegistry->get('catalog_product_price');
        $this->productPriceIndexer->reindexAll();

        $this->productsIndexer->executeFull();
        $this->algoliaHelper->waitLastTask();
    }

    public function testMultiStoreProductIndices()
    {
        // Check that every store has the right number of products
        foreach ($this->storeManager->getStores() as $store) {
            $this->algoliaHelper->setStoreId($store->getId());
            $this->assertNbOfRecordsPerStore(
                $store->getCode(),
                'products',
                $store->getCode() === 'default' ?
                    $this->assertValues->productsCountWithoutGiftcards :
                    count(self::SKUS)
            );
        }

        $defaultStore = $this->storeRepository->get('default');
        $fixtureSecondStore = $this->storeRepository->get('fixture_second_store');
        $fixtureThirdStore = $this->storeRepository->get('fixture_third_store');

        try {
            $voyageYogaBag = $this->loadProduct(self::VOYAGE_YOGA_BAG_ID, $defaultStore->getId());
        } catch (\Exception $e) {
            $this->markTestIncomplete('Product could not be found.');
        }

        $this->assertEquals(self::VOYAGE_YOGA_BAG_NAME, $voyageYogaBag->getName());

        // Change a product name at store level
        $voyageYogaBagAlt = $this->updateProduct(
            self::VOYAGE_YOGA_BAG_ID,
            $fixtureSecondStore->getId(),
            ['name' => self::VOYAGE_YOGA_BAG_NAME_ALT]
        );

        $this->assertEquals(self::VOYAGE_YOGA_BAG_NAME, $voyageYogaBag->getName());
        $this->assertEquals(self::VOYAGE_YOGA_BAG_NAME_ALT, $voyageYogaBagAlt->getName());

        $this->productsIndexer->execute([self::VOYAGE_YOGA_BAG_ID]);
        $this->algoliaHelper->waitLastTask();

        $this->algoliaHelper->setStoreId($defaultStore->getId());
        $this->assertAlgoliaRecordValues(
            $this->indexPrefix . 'default_products',
            (string) self::VOYAGE_YOGA_BAG_ID,
            ['name' => self::VOYAGE_YOGA_BAG_NAME]
        );

        $this->algoliaHelper->setStoreId($fixtureSecondStore->getId());
        $this->assertAlgoliaRecordValues(
            $this->indexPrefix . 'fixture_second_store_products',
            (string) self::VOYAGE_YOGA_BAG_ID,
            ['name' => self::VOYAGE_YOGA_BAG_NAME_ALT]
        );

        $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);

        // Unassign product from a single website (removed from test website (second and third store))
        $baseWebsite = $this->websiteRepository->get('base');

        $voyageYogaBag = $this->loadProduct(self::VOYAGE_YOGA_BAG_ID);

        $voyageYogaBag->setWebsiteIds([$baseWebsite->getId()]);
        $this->productRepository->save($voyageYogaBag);
        $this->productPriceIndexer->reindexRow(self::VOYAGE_YOGA_BAG_ID);

        $this->productsIndexer->execute([self::VOYAGE_YOGA_BAG_ID]);
        $this->algoliaHelper->waitLastTask();

        // default store should have the same number of products
        $this->algoliaHelper->setStoreId($defaultStore->getId());
        $this->assertNbOfRecordsPerStore(
            $defaultStore->getCode(),
            'products',
            $this->assertValues->productsCountWithoutGiftcards
        );

        // Stores from test website must have one less product
        $this->algoliaHelper->setStoreId($fixtureThirdStore->getId());
        $this->assertNbOfRecordsPerStore(
            $fixtureThirdStore->getCode(),
            'products',
            count(self::SKUS) - 1
        );

        $this->algoliaHelper->setStoreId($fixtureSecondStore->getId());
        $this->assertNbOfRecordsPerStore(
            $fixtureSecondStore->getCode(),
            'products',
            count(self::SKUS) - 1
        );
    }

    /**
     * Loads product by id.
     *
     * @param int $productId
     * @param int|null $storeId
     *
     * @return ProductInterface
     * @throws NoSuchEntityException
     */
    private function loadProduct(int $productId, int $storeId = null): ProductInterface
    {
        return $this->productRepository->getById($productId, true, $storeId);
    }

    /**
     * @param int $productId
     * @param int $storeId
     * @param array $values
     *
     * @return ProductInterface
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     *
     */
    private function updateProduct(int $productId, int $storeId, array $values): ProductInterface
    {
        $oldStoreId = $this->storeManager->getStore()->getId();
        $this->storeManager->setCurrentStore($storeId);
        $product = $this->loadProduct($productId, $storeId);
        foreach ($values as $attribute => $value) {
            $product->setData($attribute, $value);
        }
        $productAlt = $this->productRepository->save($product);
        $this->storeManager->setCurrentStore($oldStoreId);

        return $productAlt;
    }

    protected function tearDown(): void
    {
        $defaultStore = $this->storeRepository->get('default');

        // Restore product name in case DB is not cleaned up
        $this->updateProduct(
            self::VOYAGE_YOGA_BAG_ID,
            $defaultStore->getId(),
            [
                'name' => self::VOYAGE_YOGA_BAG_NAME,
            ]
        );

        parent::tearDown();
    }
}
