<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin;

use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Algolia\AlgoliaSearch\Plugin\QuoteItem;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;

class QuoteItemTest extends TestCase
{
    protected null|(InsightsHelper&MockObject) $insightsHelper = null;
    protected null|(AbstractItem&MockObject) $item = null;
    protected null|(OrderItem&MockObject) $orderItem = null;
    protected null|(ToOrderItem&MockObject) $subject = null;
    protected ?QuoteItem $plugin = null;

    protected function setUp(): void
    {
        $this->insightsHelper = $this->createMock(InsightsHelper::class);

        $product = $this->createMock(Product::class);
        $product->method('getStoreId')->willReturn(1);

        $this->item = $this->getMockBuilder(AbstractItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getProduct', 'getData'])
            ->getMockForAbstractClass();
        $this->item->method('getProduct')->willReturn($product);
        $this->item->method('getData')
            ->with(InsightsHelper::QUOTE_ITEM_QUERY_PARAM)
            ->willReturn('encoded_query_data');

        $this->orderItem = $this->getMockBuilder(OrderItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setData'])
            ->getMock();

        $this->subject = $this->createMock(ToOrderItem::class);

        $this->plugin = new QuoteItem($this->insightsHelper);
    }

    public function testAfterConvertCopiesQueryParamWhenOrderTrackingEnabled(): void
    {
        $this->insightsHelper->method('isOrderPlacedTracked')->with(1)->willReturn(true);

        $this->orderItem->expects($this->once())
            ->method('setData')
            ->with(InsightsHelper::QUOTE_ITEM_QUERY_PARAM, 'encoded_query_data');

        $result = $this->plugin->afterConvert($this->subject, $this->orderItem, $this->item);

        $this->assertSame($this->orderItem, $result);
    }

    public function testAfterConvertDoesNotCopyQueryParamWhenOrderTrackingDisabled(): void
    {
        $this->insightsHelper->method('isOrderPlacedTracked')->with(1)->willReturn(false);

        $this->orderItem->expects($this->never())->method('setData');

        $result = $this->plugin->afterConvert($this->subject, $this->orderItem, $this->item);

        $this->assertSame($this->orderItem, $result);
    }
}
