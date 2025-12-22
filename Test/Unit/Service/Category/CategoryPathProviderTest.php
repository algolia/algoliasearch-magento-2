<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Category;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Category\CategoryPathProvider;
use Algolia\AlgoliaSearch\Service\Category\RecordBuilder;
use Magento\Catalog\Model\Category;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CategoryPathProviderTest extends TestCase
{
    private CategoryPathProvider $categoryPathProvider;
    private ConfigHelper|MockObject $configHelper;
    private RecordBuilder|MockObject $recordBuilder;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->recordBuilder = $this->createMock(RecordBuilder::class);

        $this->categoryPathProvider = new CategoryPathProvider(
            $this->configHelper,
            $this->recordBuilder
        );
    }

    public function testGetCategoryPathDetailsReturnsSingleCategory(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([10]);

        $this->recordBuilder
            ->method('getCategoryName')
            ->with(10, $storeId)
            ->willReturn('Electronics');

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('Electronics', $result['path']);
        $this->assertEquals(0, $result['level']);
        $this->assertEquals('', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsReturnsMultiLevelPath(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([2, 10, 25]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with($storeId)
            ->willReturn(' /// ');

        $this->recordBuilder
            ->method('getCategoryName')
            ->willReturnMap([
                [2, $storeId, 'Default Category'],
                [10, $storeId, 'Electronics'],
                [25, $storeId, 'Laptops'],
            ]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('Default Category /// Electronics /// Laptops', $result['path']);
        $this->assertEquals(2, $result['level']);
        $this->assertEquals('Electronics', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsSkipsNullCategoryNames(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([1, 2, 10, 25]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with($storeId)
            ->willReturn(' / ');

        $this->recordBuilder
            ->method('getCategoryName')
            ->willReturnMap([
                [1, $storeId, null],
                [2, $storeId, 'Default Category'],
                [10, $storeId, null],
                [25, $storeId, 'Laptops'],
            ]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('Default Category / Laptops', $result['path']);
        $this->assertEquals(1, $result['level']);
        $this->assertEquals('Default Category', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsReturnsEmptyPathWhenAllCategoriesAreNull(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([1, 2]);

        $this->recordBuilder
            ->method('getCategoryName')
            ->willReturn(null);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('', $result['path']);
        $this->assertEquals('', $result['level']);
        $this->assertEquals('', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsUsesDefaultStoreIdWhenNull(): void
    {
        $category = $this->createCategoryMock([10, 20]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with(null)
            ->willReturn(' > ');

        $this->recordBuilder
            ->method('getCategoryName')
            ->willReturnMap([
                [10, null, 'Category A'],
                [20, null, 'Category B'],
            ]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category);

        $this->assertEquals('Category A > Category B', $result['path']);
        $this->assertEquals(1, $result['level']);
        $this->assertEquals('Category A', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsWithEmptySeparator(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([10, 20, 30]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with($storeId)
            ->willReturn('');

        $this->recordBuilder
            ->method('getCategoryName')
            ->willReturnMap([
                [10, $storeId, 'A'],
                [20, $storeId, 'B'],
                [30, $storeId, 'C'],
            ]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('ABC', $result['path']);
        $this->assertEquals(2, $result['level']);
        $this->assertEquals('B', $result['parentCategory']);
    }

    public function testGetCategoryPathDetailsWithTwoCategoriesReturnsCorrectParent(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([10, 20]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with($storeId)
            ->willReturn(' / ');

        $this->recordBuilder
            ->method('getCategoryName')
            ->willReturnMap([
                [10, $storeId, 'Parent'],
                [20, $storeId, 'Child'],
            ]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('Parent / Child', $result['path']);
        $this->assertEquals(1, $result['level']);
        $this->assertEquals('Parent', $result['parentCategory']);
    }

    public function testGetCategoryPageIdReturnsPath(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([10, 20, 30]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with($storeId)
            ->willReturn(' /// ');

        $this->recordBuilder
            ->method('getCategoryName')
            ->willReturnMap([
                [10, $storeId, 'Root'],
                [20, $storeId, 'Parent'],
                [30, $storeId, 'Child'],
            ]);

        $result = $this->categoryPathProvider->getCategoryPageId($category, $storeId);

        $this->assertEquals('Root /// Parent /// Child', $result);
    }

    public function testGetCategoryPageIdWithNullStoreId(): void
    {
        $category = $this->createCategoryMock([5]);

        $this->recordBuilder
            ->method('getCategoryName')
            ->with(5, null)
            ->willReturn('Single Category');

        $result = $this->categoryPathProvider->getCategoryPageId($category);

        $this->assertEquals('Single Category', $result);
    }

    public function testGetCategoryPathDetailsWithEmptyPathIds(): void
    {
        $storeId = 1;
        $category = $this->createCategoryMock([]);

        $result = $this->categoryPathProvider->getCategoryPathDetails($category, $storeId);

        $this->assertEquals('', $result['path']);
        $this->assertEquals('', $result['level']);
        $this->assertEquals('', $result['parentCategory']);
    }

    private function createCategoryMock(array $pathIds): Category|MockObject
    {
        $category = $this->createMock(Category::class);
        $category
            ->method('getPathIds')
            ->willReturn($pathIds);

        return $category;
    }
}

