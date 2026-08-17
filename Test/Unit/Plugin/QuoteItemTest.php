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

class QuoteItemTest extends TestCase
{
    protected function createObjectToTest(?InsightsHelper $insightsHelper = null): QuoteItem
    {
        return new QuoteItem($insightsHelper ?? $this->createStub(InsightsHelper::class));
    }

    private function createItemStub(): AbstractItem
    {
        $product = $this->createStub(Product::class);
        $product->method('getStoreId')->willReturn(1);

        $item = $this->createStub(AbstractItem::class);
        $item->method('getProduct')->willReturn($product);
        $item->method('getData')->willReturn('encoded_query_data');

        return $item;
    }

    public function testAfterConvertCopiesQueryParamWhenOrderTrackingEnabled(): void
    {
        $insightsHelper = $this->createStub(InsightsHelper::class);
        $insightsHelper->method('isOrderPlacedTracked')->willReturn(true);

        $orderItem = $this->getMockBuilder(OrderItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setData'])
            ->getMock();
        $orderItem->expects($this->once())
            ->method('setData')
            ->with(InsightsHelper::QUOTE_ITEM_QUERY_PARAM, 'encoded_query_data');

        $subject = $this->createStub(ToOrderItem::class);

        $plugin = $this->createObjectToTest($insightsHelper);

        $result = $plugin->afterConvert($subject, $orderItem, $this->createItemStub());

        $this->assertSame($orderItem, $result);
    }

    public function testAfterConvertDoesNotCopyQueryParamWhenOrderTrackingDisabled(): void
    {
        $insightsHelper = $this->createStub(InsightsHelper::class);
        $insightsHelper->method('isOrderPlacedTracked')->willReturn(false);

        $orderItem = $this->getMockBuilder(OrderItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setData'])
            ->getMock();
        $orderItem->expects($this->never())->method('setData');

        $subject = $this->createStub(ToOrderItem::class);

        $plugin = $this->createObjectToTest($insightsHelper);

        $result = $plugin->afterConvert($subject, $orderItem, $this->createItemStub());

        $this->assertSame($orderItem, $result);
    }
}
