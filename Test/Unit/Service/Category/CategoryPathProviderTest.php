<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Category;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Category\CategoryPathProvider;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CategoryPathProviderTest extends TestCase
{
    private CategoryPathProvider $categoryPathProvider;
    private ConfigHelper&MockObject $configHelper;
    private CategoryRepositoryInterface&MockObject $categoryRepository;
    private CategoryCollectionFactory&MockObject $categoryCollectionFactory;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->categoryCollectionFactory = $this->createMock(CategoryCollectionFactory::class);

        $this->categoryPathProvider = new CategoryPathProvider(
            $this->configHelper,
            $this->categoryRepository,
            $this->categoryCollectionFactory
        );
    }

    public function testGetCategoryPathDetailsReturnsSingleCategory(): void
    {
        $storeId = 1;
        // Example paths include root (1) and default category (2) which are filtered out by DB level > 1
        $category = $this->createCategoryMock([1, 2, 10]);

        // Only categories at DB level 2+ are returned by the collection
        $this->setupCategoryCollection([
            10 => 'Electronics',
        ]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('Electronics', $result['path']);
        $this->assertEquals(0, $result['level']); // This is the *visible* level, not the DB level - the "visible" level starts at 0
        $this->assertEquals('', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsReturnsMultiLevelPath(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([1, 2, 10, 25, 30]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with($storeId)
            ->willReturn(' /// ');

        $this->setupCategoryCollection([
            10 => 'Electronics',
            25 => 'Laptops',
            30 => 'Gaming Laptops',
        ]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('Electronics /// Laptops /// Gaming Laptops', $result['path']);
        $this->assertEquals(2, $result['level']);
        $this->assertEquals('Laptops', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsSkipsCategoriesNotInCollection(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([1, 2, 10, 25]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with($storeId)
            ->willReturn(' / ');

        $this->setupCategoryCollection([
            10 => 'Electronics',
            25 => 'Laptops',
        ]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('Electronics / Laptops', $result['path']);
        $this->assertEquals(1, $result['level']); 
        $this->assertEquals('Electronics', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsReturnsEmptyPathWhenNoCategoriesInCollection(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([1, 2]);

        $this->setupCategoryCollection([]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('', $result['path']);
        $this->assertEquals('', $result['level']);
        $this->assertEquals('', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsUsesDefaultStoreIdWhenNull(): void
    {
        $category = $this->createCategoryMock([1, 2, 10, 20]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with(null)
            ->willReturn(' > ');

        $this->setupCategoryCollection([
            10 => 'Category A',
            20 => 'Category B',
        ]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category);

        $this->assertEquals('Category A > Category B', $result['path']);
        $this->assertEquals(1, $result['level']);
        $this->assertEquals('Category A', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsWithEmptySeparator(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([1, 2, 10, 20, 30]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with($storeId)
            ->willReturn('');

        $this->setupCategoryCollection([
            10 => 'A',
            20 => 'B',
            30 => 'C',
        ]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('ABC', $result['path']);
        $this->assertEquals(2, $result['level']);
        $this->assertEquals('B', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsWithTwoCategoriesReturnsCorrectParent(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([1, 2, 10, 20]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with($storeId)
            ->willReturn(' / ');

        $this->setupCategoryCollection([
            10 => 'Parent',
            20 => 'Child',
        ]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('Parent / Child', $result['path']);
        $this->assertEquals(1, $result['level']);
        $this->assertEquals('Parent', $result['parentCategory']);
    }

    public function testGetCategoryPageIdReturnsPath(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([1, 2, 10, 20, 30]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with($storeId)
            ->willReturn(' /// ');

        $this->setupCategoryCollection([
            10 => 'Root',
            20 => 'Parent',
            30 => 'Child',
        ]);

        $result = $this->categoryPathProvider->getCategoryPageId($category, $storeId);

        $this->assertEquals('Root /// Parent /// Child', $result);
    }

    public function testGetCategoryPageIdWithNullStoreId(): void
    {
        $category = $this->createCategoryMock([1, 2, 5]);

        $this->setupCategoryCollection([
            5 => 'Single Category',
        ]);

        $result = $this->categoryPathProvider->getCategoryPageId($category);

        $this->assertEquals('Single Category', $result);
    }

    public function testGetCategoryPathDetailsWithEmptyPathIds(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([]);

        $this->setupCategoryCollection([]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('', $result['path']);
        $this->assertEquals('', $result['level']);
        $this->assertEquals('', $result['parentCategory']);
    }

    public function testGetCategoryPageIdWithCategoryId(): void
    {
        $storeId = 1;
        $categoryId = 30;

        $category = $this->createCategoryMock([1, 2, 10, 20, 30]);

        $this->categoryRepository
            ->method('get')
            ->with($categoryId, $storeId)
            ->willReturn($category);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with($storeId)
            ->willReturn(' / ');

        $this->setupCategoryCollection([
            10 => 'Root',
            20 => 'Parent',
            30 => 'Child',
        ]);

        $result = $this->categoryPathProvider->getCategoryPageId($categoryId, $storeId);

        $this->assertEquals('Root / Parent / Child', $result);
    }

    private function createCategoryMock(array $pathIds): Category&MockObject
    {
        $category = $this->createMock(Category::class);
        $category
            ->method('getPathIds')
            ->willReturn($pathIds);

        return $category;
    }

    /**
     * @param array<int, string> $categoryNameMap Map of category ID to name
     */
    private function setupCategoryCollection(array $categoryNameMap): void
    {
        $categoryMocks = [];
        foreach ($categoryNameMap as $id => $name) {
            $categoryMock = $this->createMock(Category::class);
            $categoryMock->method('getId')->willReturn($id);
            $categoryMock->method('getName')->willReturn($name);
            $categoryMocks[] = $categoryMock;
        }

        $collection = $this->createMock(CategoryCollection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setStoreId')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($categoryMocks));

        $this->categoryCollectionFactory
            ->method('create')
            ->willReturn($collection);
    }
}

