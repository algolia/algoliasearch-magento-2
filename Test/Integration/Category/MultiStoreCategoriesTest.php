<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Category;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Model\Indexer\Category;
use Algolia\AlgoliaSearch\Test\Integration\MultiStoreTestCase;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class MultiStoreCategoriesTest extends MultiStoreTestCase
{
    /** @var Category */
    protected $categoriesIndexer;

    /** @var CategoryRepositoryInterface */
    protected $categoryRepository;

    /**  @var CollectionFactory */
    private $categoryCollectionFactory;

    const BAGS_CATEGORY_ID = 4;
    const BAGS_CATEGORY_NAME = "Bags";
    const BAGS_CATEGORY_NAME_ALT = "Bags Alt";

    protected function setUp():void
    {
        parent::setUp();

        $this->categoriesIndexer = $this->objectManager->get(Category::class);
        $this->categoryRepository = $this->objectManager->get(CategoryRepositoryInterface::class);
        $this->categoryCollectionFactory = $this->objectManager->get(CollectionFactory::class);


        $this->categoriesIndexer->executeFull();
        $this->algoliaHelper->waitLastTask();
    }

    /**
     * @throws CouldNotSaveException
     * @throws ExceededRetriesException
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    public function testMultiStoreCategoryIndices()
    {
        // Check that every store has the right number of categories
        foreach ($this->storeManager->getStores() as $store) {
            $this->assertNbOfRecordsPerStore($store->getCode(), 'categories', $this->assertValues->expectedCategory);
        }

        $defaultStore = $this->storeRepository->get('default');
        $fixtureSecondStore = $this->storeRepository->get('fixture_second_store');

        $bagsCategory = $this->loadCategory(self::BAGS_CATEGORY_ID, $defaultStore->getId());

        $this->assertEquals(self::BAGS_CATEGORY_NAME, $bagsCategory->getName());

        // Change a category name at store level
        $bagsCategoryAlt = $this->updateCategory(
            self::BAGS_CATEGORY_ID,
            $fixtureSecondStore->getId(),
            ['name' => self::BAGS_CATEGORY_NAME_ALT]
        );

        $this->assertEquals(self::BAGS_CATEGORY_NAME, $bagsCategory->getName());
        $this->assertEquals(self::BAGS_CATEGORY_NAME_ALT, $bagsCategoryAlt->getName());

        $this->categoriesIndexer->execute([self::BAGS_CATEGORY_ID]);
        $this->algoliaHelper->waitLastTask();

        $this->assertAlgoliaRecordValues(
            $this->indexPrefix . 'default_categories',
            (string) self::BAGS_CATEGORY_ID,
            ['name' => self::BAGS_CATEGORY_NAME]
        );

        $this->assertAlgoliaRecordValues(
            $this->indexPrefix . 'fixture_second_store_categories',
            (string) self::BAGS_CATEGORY_ID,
            ['name' => self::BAGS_CATEGORY_NAME_ALT]
        );

        // Disable this category at store level
        $bagsCategoryAlt = $this->updateCategory(
            self::BAGS_CATEGORY_ID,
            $fixtureSecondStore->getId(),
            ['is_active' => 0]
        );

        $this->categoriesIndexer->execute([self::BAGS_CATEGORY_ID]);
        $this->algoliaHelper->waitLastTask();

        $this->assertNbOfRecordsPerStore(
            $defaultStore->getCode(),
            'categories',
            $this->assertValues->expectedCategory
        );

        $this->assertNbOfRecordsPerStore(
            $fixtureSecondStore->getCode(),
            'categories',
            $this->assertValues->expectedCategory - 1
        );
    }

    /**
     * Loads category by name.
     *
     * @param int $categoryId
     * @param int $storeId
     *
     * @return CategoryInterface
     * @throws NoSuchEntityException
     */
    private function loadCategory(int $categoryId, int $storeId): CategoryInterface
    {
        return $this->categoryRepository->get($categoryId, $storeId);
    }

    /**
     * @param int $categoryId
     * @param int $storeId
     * @param array $values
     *
     * @return CategoryInterface
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     *
     * @see Magento\Catalog\Block\Product\ListProduct\SortingTest
     */
    private function updateCategory(int $categoryId, int $storeId, array $values): CategoryInterface
    {
        $oldStoreId = $this->storeManager->getStore()->getId();
        $this->storeManager->setCurrentStore($storeId);
        $category = $this->loadCategory($categoryId, $storeId);
        foreach ($values as $attribute => $value) {
            $category->setData($attribute, $value);
        }
        $categoryAlt = $this->categoryRepository->save($category);
        $this->storeManager->setCurrentStore($oldStoreId);

        return $categoryAlt;
    }

    protected function tearDown(): void
    {
        $defaultStore = $this->storeRepository->get('default');

        // Restore category name in case DB is not cleaned up
        $this->updateCategory(
            self::BAGS_CATEGORY_ID,
            $defaultStore->getId(),
            [
                'name' => self::BAGS_CATEGORY_NAME,
                'is_active' => 1
            ]
        );

        parent::tearDown();
    }
}
