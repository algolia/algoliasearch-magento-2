<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Category;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Category\CategoryPathProvider;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use PHPUnit\Framework\TestCase;

class CategoryPathProviderTest extends TestCase
{
    protected function createObjectToTest(
        ?ConfigHelper $configHelper = null,
        ?CategoryRepositoryInterface $categoryRepository = null,
        ?CategoryCollectionFactory $categoryCollectionFactory = null,
    ): CategoryPathProvider {
        return new CategoryPathProvider(
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $categoryRepository ?? $this->createStub(CategoryRepositoryInterface::class),
            $categoryCollectionFactory ?? $this->createStub(CategoryCollectionFactory::class),
        );
    }

    public function testGetCategoryPathDetailsReturnsSingleCategory(): void
    {
        $storeId = 1;
        // Example paths include root (1) and default category (2) which are filtered out by DB level > 1
        $category = $this->createCategoryStub([1, 2, 10]);

        // Only categories at DB level 2+ are returned by the collection
        $categoryCollectionFactory = $this->createCategoryCollectionFactoryStub([
            10 => 'Electronics',
        ]);

        $categoryPathProvider = $this->createObjectToTest(categoryCollectionFactory: $categoryCollectionFactory);

        $result = $categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('Electronics', $result['path']);
        $this->assertEquals(0, $result['level']); // This is the *visible* level, not the DB level - the "visible" level starts at 0
        $this->assertEquals('', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsReturnsMultiLevelPath(): void
    {
        $storeId = 1;
        $category = $this->createCategoryStub([1, 2, 10, 25, 30]);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getCategorySeparator')->willReturn(' /// ');

        $categoryCollectionFactory = $this->createCategoryCollectionFactoryStub([
            10 => 'Electronics',
            25 => 'Laptops',
            30 => 'Gaming Laptops',
        ]);

        $categoryPathProvider = $this->createObjectToTest($configHelper, categoryCollectionFactory: $categoryCollectionFactory);

        $result = $categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('Electronics /// Laptops /// Gaming Laptops', $result['path']);
        $this->assertEquals(2, $result['level']);
        $this->assertEquals('Laptops', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsSkipsCategoriesNotInCollection(): void
    {
        $storeId = 1;
        $category = $this->createCategoryStub([1, 2, 10, 25]);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getCategorySeparator')->willReturn(' / ');

        $categoryCollectionFactory = $this->createCategoryCollectionFactoryStub([
            10 => 'Electronics',
            25 => 'Laptops',
        ]);

        $categoryPathProvider = $this->createObjectToTest($configHelper, categoryCollectionFactory: $categoryCollectionFactory);

        $result = $categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('Electronics / Laptops', $result['path']);
        $this->assertEquals(1, $result['level']);
        $this->assertEquals('Electronics', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsReturnsEmptyPathWhenNoCategoriesInCollection(): void
    {
        $storeId = 1;
        $category = $this->createCategoryStub([1, 2]);

        $categoryCollectionFactory = $this->createCategoryCollectionFactoryStub([]);

        $categoryPathProvider = $this->createObjectToTest(categoryCollectionFactory: $categoryCollectionFactory);

        $result = $categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('', $result['path']);
        $this->assertEquals('', $result['level']);
        $this->assertEquals('', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsUsesDefaultStoreIdWhenNull(): void
    {
        $category = $this->createCategoryStub([1, 2, 10, 20]);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getCategorySeparator')->willReturn(' > ');

        $categoryCollectionFactory = $this->createCategoryCollectionFactoryStub([
            10 => 'Category A',
            20 => 'Category B',
        ]);

        $categoryPathProvider = $this->createObjectToTest($configHelper, categoryCollectionFactory: $categoryCollectionFactory);

        $result = $categoryPathProvider->getCategoryPathDetails($category);

        $this->assertEquals('Category A > Category B', $result['path']);
        $this->assertEquals(1, $result['level']);
        $this->assertEquals('Category A', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsWithEmptySeparator(): void
    {
        $storeId = 1;
        $category = $this->createCategoryStub([1, 2, 10, 20, 30]);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getCategorySeparator')->willReturn('');

        $categoryCollectionFactory = $this->createCategoryCollectionFactoryStub([
            10 => 'A',
            20 => 'B',
            30 => 'C',
        ]);

        $categoryPathProvider = $this->createObjectToTest($configHelper, categoryCollectionFactory: $categoryCollectionFactory);

        $result = $categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('ABC', $result['path']);
        $this->assertEquals(2, $result['level']);
        $this->assertEquals('B', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsWithTwoCategoriesReturnsCorrectParent(): void
    {
        $storeId = 1;
        $category = $this->createCategoryStub([1, 2, 10, 20]);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getCategorySeparator')->willReturn(' / ');

        $categoryCollectionFactory = $this->createCategoryCollectionFactoryStub([
            10 => 'Parent',
            20 => 'Child',
        ]);

        $categoryPathProvider = $this->createObjectToTest($configHelper, categoryCollectionFactory: $categoryCollectionFactory);

        $result = $categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('Parent / Child', $result['path']);
        $this->assertEquals(1, $result['level']);
        $this->assertEquals('Parent', $result['parentCategory']);
    }

    public function testGetCategoryPageIdReturnsPath(): void
    {
        $storeId = 1;
        $category = $this->createCategoryStub([1, 2, 10, 20, 30]);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getCategorySeparator')->willReturn(' /// ');

        $categoryCollectionFactory = $this->createCategoryCollectionFactoryStub([
            10 => 'Root',
            20 => 'Parent',
            30 => 'Child',
        ]);

        $categoryPathProvider = $this->createObjectToTest($configHelper, categoryCollectionFactory: $categoryCollectionFactory);

        $result = $categoryPathProvider->getCategoryPageId($category, $storeId);

        $this->assertEquals('Root /// Parent /// Child', $result);
    }

    public function testGetCategoryPageIdWithNullStoreId(): void
    {
        $category = $this->createCategoryStub([1, 2, 5]);

        $categoryCollectionFactory = $this->createCategoryCollectionFactoryStub([
            5 => 'Single Category',
        ]);

        $categoryPathProvider = $this->createObjectToTest(categoryCollectionFactory: $categoryCollectionFactory);

        $result = $categoryPathProvider->getCategoryPageId($category);

        $this->assertEquals('Single Category', $result);
    }

    public function testGetCategoryPathDetailsWithEmptyPathIds(): void
    {
        $storeId = 1;
        $category = $this->createCategoryStub([]);

        $categoryCollectionFactory = $this->createCategoryCollectionFactoryStub([]);

        $categoryPathProvider = $this->createObjectToTest(categoryCollectionFactory: $categoryCollectionFactory);

        $result = $categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('', $result['path']);
        $this->assertEquals('', $result['level']);
        $this->assertEquals('', $result['parentCategory']);
    }

    public function testGetCategoryPageIdWithCategoryId(): void
    {
        $storeId = 1;
        $categoryId = 30;

        $category = $this->createCategoryStub([1, 2, 10, 20, 30]);

        $categoryRepository = $this->createStub(CategoryRepositoryInterface::class);
        $categoryRepository->method('get')->willReturn($category);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getCategorySeparator')->willReturn(' / ');

        $categoryCollectionFactory = $this->createCategoryCollectionFactoryStub([
            10 => 'Root',
            20 => 'Parent',
            30 => 'Child',
        ]);

        $categoryPathProvider = $this->createObjectToTest($configHelper, $categoryRepository, $categoryCollectionFactory);

        $result = $categoryPathProvider->getCategoryPageId($categoryId, $storeId);

        $this->assertEquals('Root / Parent / Child', $result);
    }

    private function createCategoryStub(array $pathIds): Category
    {
        $category = $this->createStub(Category::class);
        $category->method('getPathIds')->willReturn($pathIds);

        return $category;
    }

    /**
     * @param array<int, string> $categoryNameMap Map of category ID to name
     */
    private function createCategoryCollectionFactoryStub(array $categoryNameMap): CategoryCollectionFactory
    {
        $categoryStubs = [];
        foreach ($categoryNameMap as $id => $name) {
            $categoryStub = $this->createStub(Category::class);
            $categoryStub->method('getId')->willReturn($id);
            $categoryStub->method('getName')->willReturn($name);
            $categoryStubs[] = $categoryStub;
        }

        $collection = $this->createStub(CategoryCollection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setStoreId')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($categoryStubs));

        $categoryCollectionFactory = $this->createStub(CategoryCollectionFactory::class);
        $categoryCollectionFactory->method('create')->willReturn($collection);

        return $categoryCollectionFactory;
    }
}
