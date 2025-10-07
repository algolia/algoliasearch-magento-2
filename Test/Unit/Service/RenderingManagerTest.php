<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
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
    protected ?ConfigHelper $configHelper;
    protected ?AutocompleteHelper $autocompleteConfigHelper;
    protected ?InstantSearchHelper $instantSearchConfigHelper;
    protected ?CurrentCategory $category;
    protected ?StoreManagerInterface $storeManager;

    protected ?RenderingManager $renderingManager;

    public function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->autocompleteConfigHelper = $this->createMock(AutocompleteHelper::class);
        $this->instantSearchConfigHelper = $this->createMock(InstantSearchHelper::class);
        $this->category = $this->createMock(CurrentCategory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $this->renderingManager = new RenderingManager(
            $this->configHelper,
            $this->autocompleteConfigHelper,
            $this->instantSearchConfigHelper,
            $this->category,
            $this->storeManager
        );
    }

    /**
     * @dataProvider frontendValuesProvider
     */
    public function testFrontendAssets($isAutocompleteEnabled, $isInstantSearchEnabled, $isLayoutUpdated): void
    {
        $this->autocompleteConfigHelper->method('isEnabled')->willReturn($isAutocompleteEnabled);
        $this->instantSearchConfigHelper->method('isEnabled')->willReturn($isInstantSearchEnabled);

        $layout = $this->createMock(Layout::class);
        $update = $this->createMock(ProcessorInterface::class);
        $layout->method('getUpdate')->willReturn($update);

        if ($isLayoutUpdated) {
            $update->expects($this->once())
                ->method('addHandle')
                ->with('algolia_search_handle');
        } else {
            $update->expects($this->never())
                ->method('addHandle');
        }

        $this->renderingManager->handleFrontendAssets($layout, 0);
    }

    /**
     * @dataProvider backendValuesProvider
     */
    public function testBackendRendering($actionName, $preventBackendRendering, $isLayoutUpdated): void
    {
        $this->autocompleteConfigHelper->method('isEnabled')->willReturn(true);
        $this->instantSearchConfigHelper->method('isEnabled')->willReturn(true);
        $this->configHelper->method('preventBackendRendering')->willReturn($preventBackendRendering);

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
     * @dataProvider displayValuesProvider
     */
    public function testDisplayMode($displayMode, $categoryDisplayMode, $isLayoutUpdated): void
    {
        $this->autocompleteConfigHelper->method('isEnabled')->willReturn(true);
        $this->instantSearchConfigHelper->method('isEnabled')->willReturn(true);
        $this->instantSearchConfigHelper->method('shouldReplaceCategories')->willReturn(true);
        $this->configHelper->method('preventBackendRendering')->willReturn(true);
        $this->configHelper->method('getBackendRenderingDisplayMode')->willReturn($displayMode);

        $currentCategory = $this->createMock(Category::class);
        $this->category->method('get')->willReturn($currentCategory);
        $currentCategory->method('getId')->willReturn(1);
        $currentCategory->method('getDisplayMode')->willReturn($categoryDisplayMode);

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

        $this->renderingManager->handleBackendRendering($layout, 'catalog_category_view', 0);
    }

    public static function frontendValuesProvider(): array
    {
        return [
            ['isAutocompleteEnabled' => true, 'isInstantSearchEnabled' => true, 'isLayoutUpdated' => true],
            ['isAutocompleteEnabled' => false, 'isInstantSearchEnabled' => true, 'isLayoutUpdated' => true],
            ['isAutocompleteEnabled' => true, 'isInstantSearchEnabled' => false, 'isLayoutUpdated' => true],
            ['isAutocompleteEnabled' => false, 'isInstantSearchEnabled' => false, 'isLayoutUpdated' => false]
        ];
    }

    public static function backendValuesProvider(): array
    {
        return [
            ['actionName' => 'catalog_category_view','preventBackendRendering' => true, 'isLayoutUpdated' => true],
            ['actionName' => 'catalogsearch_result_index', 'preventBackendRendering' => true, 'isLayoutUpdated' => true],
            ['actionName' => 'foo_bar', 'preventBackendRendering' => true, 'isLayoutUpdated' => false],
            ['actionName' => 'catalog_category_view', 'preventBackendRendering' => false, 'isLayoutUpdated' => false]
        ];
    }

    public static function displayValuesProvider(): array
    {
        return [
            ['displayMode' => 'all', 'categoryDisplayMode' => 'PAGE',  'isLayoutUpdated' => true],
            ['displayMode' => 'all', 'categoryDisplayMode' => 'PRODUCTS',  'isLayoutUpdated' => true],
            ['displayMode' => 'all', 'categoryDisplayMode' => 'PRODUCTS_AND_PAGE',  'isLayoutUpdated' => true],
            ['displayMode' => 'only_products', 'categoryDisplayMode' => 'PAGE', 'isLayoutUpdated' => false],
            ['displayMode' => 'only_products', 'categoryDisplayMode' => 'PRODUCTS', 'isLayoutUpdated' => true],
            ['displayMode' => 'only_products', 'categoryDisplayMode' => 'PRODUCTS_AND_PAGE', 'isLayoutUpdated' => true],
        ];
    }
}
