<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\Query;

use Algolia\AlgoliaSearch\Block\Adminhtml\Query\Merchandising;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class MerchandisingTest extends TestCase
{
    protected null|(Merchandising&MockObject) $block = null;
    protected null|(StoreManagerInterface&MockObject) $storeManager = null;
    protected null|(RequestInterface&MockObject) $request = null;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->request = $this->createMock(RequestInterface::class);

        $this->block = $this->getMockBuilder(Merchandising::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRequest'])
            ->getMock();

        $this->block->method('getRequest')->willReturn($this->request);
        $this->setPrivateProperty($this->block, 'storeManager', $this->storeManager);
    }

    public function testGetCurrentStoreReturnsStoreForRequestedStoreId(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $this->request->method('getParam')->with('store')->willReturn(3);
        $this->storeManager->method('getStore')->with(3)->willReturn($store);

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
