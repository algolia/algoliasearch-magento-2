<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\Query;

use Algolia\AlgoliaSearch\Block\Adminhtml\Query\Merchandising;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class MerchandisingTest extends TestCase
{
    protected function createObjectToTest(
        ?StoreManagerInterface $storeManager = null,
        ?RequestInterface $request = null,
    ): Merchandising {
        $block = $this->createPartialMock(Merchandising::class, ['getRequest']);
        $block->expects($this->once())
            ->method('getRequest')
            ->willReturn($request ?? $this->createStub(RequestInterface::class));

        $this->setPrivateProperty(
            $block,
            'storeManager',
            $storeManager ?? $this->createStub(StoreManagerInterface::class)
        );

        return $block;
    }

    public function testGetCurrentStoreReturnsStoreForRequestedStoreId(): void
    {
        $store = $this->createStub(StoreInterface::class);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('store')->willReturn(3);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(3)->willReturn($store);

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
