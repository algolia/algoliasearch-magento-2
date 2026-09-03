<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Registry\CurrentCategory;
use Algolia\AlgoliaSearch\Service\RenderingManager;
use Magento\Catalog\Model\Category;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Layout\ProcessorInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RenderingManagerTest extends TestCase
{
    protected function createObjectToTest(
        ?AutocompleteHelper $autocompleteConfigHelper = null,
        ?InstantSearchHelper $instantSearchConfigHelper = null,
        ?CurrentCategory $category = null,
    ): RenderingManager {
        return new RenderingManager(
            $autocompleteConfigHelper ?? $this->createStub(AutocompleteHelper::class),
            $instantSearchConfigHelper ?? $this->createStub(InstantSearchHelper::class),
            $category ?? $this->createStub(CurrentCategory::class),
        );
    }

    #[DataProvider('backendValuesProvider')]
    public function testBackendRendering($actionName, $isLayoutUpdated): void
    {
        $autocompleteConfigHelper = $this->createStub(AutocompleteHelper::class);
        $autocompleteConfigHelper->method('isEnabled')->willReturn(true);

        $instantSearchConfigHelper = $this->createStub(InstantSearchHelper::class);
        $instantSearchConfigHelper->method('isEnabled')->willReturn(true);

        $update = $this->createMock(ProcessorInterface::class);
        if ($isLayoutUpdated) {
            $update->expects($this->once())
                ->method('addHandle')
                ->with('algolia_search_handle_prevent_backend_rendering');
        } else {
            $update->expects($this->never())
                ->method('addHandle');
        }

        $layout = $this->createStub(Layout::class);
        $layout->method('getUpdate')->willReturn($update);

        $renderingManager = $this->createObjectToTest($autocompleteConfigHelper, $instantSearchConfigHelper);

        $renderingManager->handleBackendRendering($layout, $actionName, 0);
    }

    #[DataProvider('shouldPreventBackendRenderingProvider')]
    public function testShouldPreventBackendRendering(
        string $actionName,
        bool $isInstantSearchEnabled,
        ?int $categoryId,
        bool $shouldReplaceCategories,
        ?string $categoryDisplayMode,
        bool $expectedResult
    ): void {
        $instantSearchConfigHelper = $this->createStub(InstantSearchHelper::class);
        $instantSearchConfigHelper->method('isEnabled')->willReturn($isInstantSearchEnabled);
        $instantSearchConfigHelper->method('shouldReplaceCategories')->willReturn($shouldReplaceCategories);

        $currentCategory = $this->createStub(Category::class);
        $currentCategory->method('getId')->willReturn($categoryId);
        $currentCategory->method('getDisplayMode')->willReturn($categoryDisplayMode);

        $category = $this->createStub(CurrentCategory::class);
        $category->method('get')->willReturn($currentCategory);

        $renderingManager = $this->createObjectToTest(instantSearchConfigHelper: $instantSearchConfigHelper, category: $category);

        $this->assertSame($expectedResult, $renderingManager->shouldPreventBackendRendering($actionName, 0));
    }

    public static function shouldPreventBackendRenderingProvider(): array
    {
        return [
            'InstantSearch disabled' => [
                'actionName' => 'catalog_category_view',
                'isInstantSearchEnabled' => false,
                'categoryId' => null,
                'shouldReplaceCategories' => false,
                'categoryDisplayMode' => null,
                'expectedResult' => false,
            ],
            'Not a search page' => [
                'actionName' => 'cms_index_index',
                'isInstantSearchEnabled' => true,
                'categoryId' => null,
                'shouldReplaceCategories' => false,
                'categoryDisplayMode' => null,
                'expectedResult' => false,
            ],
            'Search results page with InstantSearch' => [
                'actionName' => 'catalogsearch_result_index',
                'isInstantSearchEnabled' => true,
                'categoryId' => null,
                'shouldReplaceCategories' => false,
                'categoryDisplayMode' => null,
                'expectedResult' => true,
            ],
            'Category page - replace categories disabled' => [
                'actionName' => 'catalog_category_view',
                'isInstantSearchEnabled' => true,
                'categoryId' => 1,
                'shouldReplaceCategories' => false,
                'categoryDisplayMode' => Category::DM_PRODUCT,
                'expectedResult' => false,
            ],
            'Category page - display mode PAGE (static block only)' => [
                'actionName' => 'catalog_category_view',
                'isInstantSearchEnabled' => true,
                'categoryId' => 1,
                'shouldReplaceCategories' => true,
                'categoryDisplayMode' => Category::DM_PAGE,
                'expectedResult' => false,
            ],
            'Category page - display mode PRODUCTS' => [
                'actionName' => 'catalog_category_view',
                'isInstantSearchEnabled' => true,
                'categoryId' => 1,
                'shouldReplaceCategories' => true,
                'categoryDisplayMode' => Category::DM_PRODUCT,
                'expectedResult' => true,
            ],
            'Category page - display mode PRODUCTS_AND_PAGE' => [
                'actionName' => 'catalog_category_view',
                'isInstantSearchEnabled' => true,
                'categoryId' => 1,
                'shouldReplaceCategories' => true,
                'categoryDisplayMode' => Category::DM_MIXED,
                'expectedResult' => true,
            ],
        ];
    }

    public static function backendValuesProvider(): array
    {
        return [
            ['actionName' => 'catalog_category_view', 'isLayoutUpdated' => true],
            ['actionName' => 'catalogsearch_result_index', 'isLayoutUpdated' => true],
            ['actionName' => 'foo_bar', 'isLayoutUpdated' => false],
        ];
    }
}
