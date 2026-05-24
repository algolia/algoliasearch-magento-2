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
use PHPUnit\Framework\MockObject\MockObject;

class MerchandisingTest extends TestCase
{
    protected null|(Merchandising&MockObject) $block = null;
    protected null|(CurrentCategory&MockObject) $currentCategory = null;
    protected null|(Category&MockObject) $category = null;
    protected null|(StoreManagerInterface&MockObject) $storeManager = null;
    protected null|(RequestInterface&MockObject) $request = null;

    protected function setUp(): void
    {
        $this->currentCategory = $this->createMock(CurrentCategory::class);
        $this->category = $this->createMock(Category::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->request = $this->createMock(RequestInterface::class);

        $this->block = $this->getMockBuilder(Merchandising::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRequest'])
            ->getMock();

        $this->block->method('getRequest')->willReturn($this->request);
        $this->setPrivateProperty($this->block, 'currentCategory', $this->currentCategory);
        $this->setPrivateProperty($this->block, 'storeManager', $this->storeManager);
    }

    public function testIsRootCategoryReturnsFalseWhenPathIsEmpty(): void
    {
        $this->category->method('getPath')->willReturn('');
        $this->currentCategory->method('get')->willReturn($this->category);
        $this->assertFalse($this->block->isRootCategory());
    }

    public function testIsRootCategoryReturnsTrueWhenPathHasTwoParts(): void
    {
        $this->category->method('getPath')->willReturn('1/2');
        $this->currentCategory->method('get')->willReturn($this->category);
        $this->assertTrue($this->block->isRootCategory());
    }

    public function testIsRootCategoryReturnsFalseWhenPathHasMoreThanTwoParts(): void
    {
        $this->category->method('getPath')->willReturn('1/2/3');
        $this->currentCategory->method('get')->willReturn($this->category);
        $this->assertFalse($this->block->isRootCategory());
    }

    public function testCanDisplayProductsReturnsFalseWhenDisplayModeIsPage(): void
    {
        $this->currentCategory->method('get')->willReturn($this->category);
        $this->category->method('getDisplayMode')->willReturn(Category::DM_PAGE);
        $this->assertFalse($this->block->canDisplayProducts());
    }

    public function testCanDisplayProductsReturnsTrueWhenDisplayModeIsNotPage(): void
    {
        $this->currentCategory->method('get')->willReturn($this->category);
        $this->category->method('getDisplayMode')->willReturn('PRODUCT');
        $this->assertTrue($this->block->canDisplayProducts());
    }

    public function testGetCurrentStoreReturnsStoreForRequestedStoreId(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $this->request->method('getParam')->with('store')->willReturn(2);
        $this->storeManager->method('getStore')->with(2)->willReturn($store);

        $this->assertSame($store, $this->block->getCurrentStore());
    }

    public function testGetCurrentStoreReturnsDefaultStoreWhenNoStoreParam(): void
    {
        $defaultStore = $this->createMock(StoreInterface::class);
        $this->request->method('getParam')->with('store')->willReturn(null);
        $this->storeManager->method('getDefaultStoreView')->willReturn($defaultStore);

        $this->assertSame($defaultStore, $this->block->getCurrentStore());
    }
}
