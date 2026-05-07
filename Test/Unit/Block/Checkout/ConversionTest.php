<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Checkout;

use Algolia\AlgoliaSearch\Block\Checkout\Conversion;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use PHPUnit\Framework\MockObject\MockObject;

class ConversionTest extends TestCase
{
    protected null|(Conversion&MockObject) $block = null;
    protected null|(Session&MockObject) $checkoutSession = null;
    protected null|(ConfigHelper&MockObject) $configHelper = null;
    protected null|(Order&MockObject) $order = null;

    protected function setUp(): void
    {
        $this->checkoutSession = $this->createMock(Session::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->order = $this->createMock(Order::class);

        $this->block = $this->getMockBuilder(Conversion::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->setPrivateProperty($this->block, 'checkoutSession', $this->checkoutSession);
        $this->setPrivateProperty($this->block, 'configHelper', $this->configHelper);
        $this->checkoutSession->method('getLastRealOrder')->willReturn($this->order);
    }

    public function testGetOrderItemsConversionJsonExcludesItemsWithoutQueryParam(): void
    {
        $item = $this->createMock(Item::class);
        $item->method('hasData')->with(InsightsHelper::QUOTE_ITEM_QUERY_PARAM)->willReturn(false);

        $this->order->method('getAllVisibleItems')->willReturn([$item]);

        $this->assertSame('[]', $this->block->getOrderItemsConversionJson());
    }

    public function testGetOrderItemsConversionJsonIncludesItemsWithQueryParam(): void
    {
        $queryData = json_encode(['queryID' => 'abc123', 'position' => 1]);

        $item = $this->createMock(Item::class);
        $item->method('hasData')->with(InsightsHelper::QUOTE_ITEM_QUERY_PARAM)->willReturn(true);
        $item->method('getData')->with(InsightsHelper::QUOTE_ITEM_QUERY_PARAM)->willReturn($queryData);
        $item->method('getProductId')->willReturn(42);

        $this->order->method('getAllVisibleItems')->willReturn([$item]);

        $result = json_decode($this->block->getOrderItemsConversionJson());
        $this->assertObjectHasProperty('42', $result);
        $this->assertEquals('abc123', $result->{'42'}->queryID);
    }

    public function testToHtmlReturnsEmptyStringWhenConversionAnalyticsDisabled(): void
    {
        $this->order->method('getStoreId')->willReturn(1);
        $this->configHelper->method('isClickConversionAnalyticsEnabled')->with(1)->willReturn(false);

        $this->assertSame('', $this->block->toHtml());
    }

    public function testToHtmlReturnsEmptyStringWhenConversionModeIsNotPurchase(): void
    {
        $this->order->method('getStoreId')->willReturn(1);
        $this->configHelper->method('isClickConversionAnalyticsEnabled')->with(1)->willReturn(true);
        $this->configHelper->method('getConversionAnalyticsMode')->with(1)->willReturn('click');

        $this->assertSame('', $this->block->toHtml());
    }
}
