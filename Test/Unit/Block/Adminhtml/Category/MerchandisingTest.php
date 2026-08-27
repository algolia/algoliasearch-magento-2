<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\Category;

use Algolia\AlgoliaSearch\Block\Adminhtml\Category\Merchandising;
use Algolia\AlgoliaSearch\Registry\CurrentCategory;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class MerchandisingTest extends TestCase
{
    protected function createObjectToTest(
        ?CurrentCategory $currentCategory = null,
        ?StoreManagerInterface $storeManager = null,
        ?RequestInterface $request = null,
    ): Merchandising {
        $block = (new \ReflectionClass(Merchandising::class))->newInstanceWithoutConstructor();

        $this->setPrivateProperty(
            $block,
            '_request',
            $request ?? $this->createStub(RequestInterface::class)
        );

        $this->setPrivateProperty(
            $block,
            'currentCategory',
            $currentCategory ?? $this->createStub(CurrentCategory::class)
        );
        $this->setPrivateProperty(
            $block,
            'storeManager',
            $storeManager ?? $this->createStub(StoreManagerInterface::class)
        );

        return $block;
    }

    public function testIsRootCategoryReturnsFalseWhenPathIsEmpty(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getPath')->willReturn('');

        $currentCategory = $this->createStub(CurrentCategory::class);
        $currentCategory->method('get')->willReturn($category);

        $block = $this->createObjectToTest(currentCategory: $currentCategory);

        $this->assertFalse($block->isRootCategory());
    }

    public function testIsRootCategoryReturnsTrueWhenPathHasTwoParts(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getPath')->willReturn('1/2');

        $currentCategory = $this->createStub(CurrentCategory::class);
        $currentCategory->method('get')->willReturn($category);

        $block = $this->createObjectToTest(currentCategory: $currentCategory);

        $this->assertTrue($block->isRootCategory());
    }

    public function testIsRootCategoryReturnsFalseWhenPathHasMoreThanTwoParts(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getPath')->willReturn('1/2/3');

        $currentCategory = $this->createStub(CurrentCategory::class);
        $currentCategory->method('get')->willReturn($category);

        $block = $this->createObjectToTest(currentCategory: $currentCategory);

        $this->assertFalse($block->isRootCategory());
    }

    public function testCanDisplayProductsReturnsFalseWhenDisplayModeIsPage(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getDisplayMode')->willReturn(Category::DM_PAGE);

        $currentCategory = $this->createStub(CurrentCategory::class);
        $currentCategory->method('get')->willReturn($category);

        $block = $this->createObjectToTest(currentCategory: $currentCategory);

        $this->assertFalse($block->canDisplayProducts());
    }

    public function testCanDisplayProductsReturnsTrueWhenDisplayModeIsNotPage(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getDisplayMode')->willReturn('PRODUCT');

        $currentCategory = $this->createStub(CurrentCategory::class);
        $currentCategory->method('get')->willReturn($category);

        $block = $this->createObjectToTest(currentCategory: $currentCategory);

        $this->assertTrue($block->canDisplayProducts());
    }

    public function testGetCurrentStoreReturnsStoreForRequestedStoreId(): void
    {
        $store = $this->createStub(StoreInterface::class);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('store')->willReturn(2);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(2)->willReturn($store);

        $block = $this->createObjectToTest(storeManager: $storeManager, request: $request);

        $this->assertSame($store, $block->getCurrentStore());
    }

    public function testGetCurrentStoreReturnsDefaultStoreWhenNoStoreParam(): void
    {
        $defaultStore = $this->createStub(StoreInterface::class);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('store')->willReturn(null);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getDefaultStoreView')->willReturn($defaultStore);

        $block = $this->createObjectToTest(storeManager: $storeManager, request: $request);

        $this->assertSame($defaultStore, $block->getCurrentStore());
    }
}
