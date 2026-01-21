<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Registry\CurrentCategory;
use Algolia\AlgoliaSearch\Service\RenderingManager;
use Magento\Catalog\Model\Category;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Layout\ProcessorInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class RenderingManagerTest extends TestCase
{
    protected ?AutocompleteHelper $autocompleteConfigHelper;
    protected ?InstantSearchHelper $instantSearchConfigHelper;
    protected ?CurrentCategory $category;
    protected ?StoreManagerInterface $storeManager;

    protected ?RenderingManager $renderingManager;

    public function setUp(): void
    {
        $this->autocompleteConfigHelper = $this->createMock(AutocompleteHelper::class);
        $this->instantSearchConfigHelper = $this->createMock(InstantSearchHelper::class);
        $this->category = $this->createMock(CurrentCategory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $this->renderingManager = new RenderingManager(
            $this->autocompleteConfigHelper,
            $this->instantSearchConfigHelper,
            $this->category,
            $this->storeManager
        );
    }

    /**
     * @dataProvider backendValuesProvider
     */
    public function testBackendRendering($actionName, $isLayoutUpdated): void
    {
        $this->autocompleteConfigHelper->method('isEnabled')->willReturn(true);
        $this->instantSearchConfigHelper->method('isEnabled')->willReturn(true);

        $layout = $this->createMock(Layout::class);
        $update = $this->createMock(ProcessorInterface::class);
        $layout->method('getUpdate')->willReturn($update);

        if ($isLayoutUpdated) {
            $update->expects($this->once())
                ->method('addHandle')
                ->with('algolia_search_handle_prevent_backend_rendering');
        } else {
            $update->expects($this->never())
                ->method('addHandle');
        }

        $this->renderingManager->handleBackendRendering($layout, $actionName, 0);
    }

    /**
     * @dataProvider shouldPreventBackendRenderingProvider
     */
    public function testShouldPreventBackendRendering(
        string $actionName,
        bool $isInstantSearchEnabled,
        ?int $categoryId,
        bool $shouldReplaceCategories,
        ?string $categoryDisplayMode,
        bool $expectedResult
    ): void {
        $this->instantSearchConfigHelper->method('isEnabled')->willReturn($isInstantSearchEnabled);
        $this->instantSearchConfigHelper->method('shouldReplaceCategories')->willReturn($shouldReplaceCategories);

        $currentCategory = $this->createMock(Category::class);
        $this->category->method('get')->willReturn($currentCategory);
        $currentCategory->method('getId')->willReturn($categoryId);
        $currentCategory->method('getDisplayMode')->willReturn($categoryDisplayMode);

        $this->assertSame($expectedResult, $this->renderingManager->shouldPreventBackendRendering($actionName, 0));
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
            ['actionName' => 'foo_bar', 'isLayoutUpdated' => false]
        ];
    }
}
