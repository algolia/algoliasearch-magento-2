<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Cart;

use Algolia\AlgoliaSearch\Block\Cart\Recommend;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\MockObject\MockObject;

class RecommendTest extends TestCase
{
    protected null|(Recommend&MockObject) $block = null;
    protected null|(Session&MockObject) $checkoutSession = null;
    protected null|(ConfigHelper&MockObject) $configHelper = null;
    protected null|(Quote&MockObject) $quote = null;

    protected function setUp(): void
    {
        $this->checkoutSession = $this->createMock(Session::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->quote = $this->createMock(Quote::class);

        $this->block = $this->getMockBuilder(Recommend::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->setPrivateProperty($this->block, 'checkoutSession', $this->checkoutSession);
        $this->setPrivateProperty($this->block, 'configHelper', $this->configHelper);

        $this->checkoutSession->method('getQuote')->willReturn($this->quote);
    }

    public function testGetAllCartItemsReturnsEmptyArrayWhenCartIsEmpty(): void
    {
        $this->quote->method('getAllVisibleItems')->willReturn([]);
        $this->assertSame([], $this->block->getAllCartItems());
    }

    public function testGetAllCartItemsReturnsProductIdsFromCart(): void
    {
        $item1 = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->addMethods(['getProductId'])
            ->getMock();
        $item1->method('getProductId')->willReturn(10);

        $item2 = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->addMethods(['getProductId'])
            ->getMock();
        $item2->method('getProductId')->willReturn(20);

        $this->quote->method('getAllVisibleItems')->willReturn([$item1, $item2]);

        $this->assertSame([10, 20], $this->block->getAllCartItems());
    }

    public function testGetAllCartItemsDeduplicatesProductIds(): void
    {
        $item1 = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->addMethods(['getProductId'])
            ->getMock();
        $item1->method('getProductId')->willReturn(10);

        $item2 = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->addMethods(['getProductId'])
            ->getMock();
        $item2->method('getProductId')->willReturn(10);

        $this->quote->method('getAllVisibleItems')->willReturn([$item1, $item2]);

        $result = $this->block->getAllCartItems();
        $this->assertCount(1, $result);
        $this->assertContains(10, $result);
    }
}
