<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Checkout;

use Algolia\AlgoliaSearch\Block\Checkout\Conversion;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;

class ConversionTest extends TestCase
{
    protected function createObjectToTest(
        ?Session $checkoutSession = null,
        ?ConfigHelper $configHelper = null,
    ): Conversion {
        $context = $this->createStub(Context::class);

        return new Conversion(
            $context,
            $checkoutSession ?? $this->createStub(Session::class),
            $configHelper ?? $this->createStub(ConfigHelper::class),
        );
    }

    public function testGetOrderItemsConversionJsonExcludesItemsWithoutQueryParam(): void
    {
        $item = $this->createStub(Item::class);
        $item->method('hasData')->willReturn(false);

        $order = $this->createStub(Order::class);
        $order->method('getAllVisibleItems')->willReturn([$item]);

        $checkoutSession = $this->createStub(Session::class);
        $checkoutSession->method('getLastRealOrder')->willReturn($order);

        $block = $this->createObjectToTest(checkoutSession: $checkoutSession);

        $this->assertSame('[]', $block->getOrderItemsConversionJson());
    }

    public function testGetOrderItemsConversionJsonIncludesItemsWithQueryParam(): void
    {
        $queryData = json_encode(['queryID' => 'abc123', 'position' => 1]);

        $item = $this->createStub(Item::class);
        $item->method('hasData')->willReturn(true);
        $item->method('getData')->willReturn($queryData);
        $item->method('getProductId')->willReturn(42);

        $order = $this->createStub(Order::class);
        $order->method('getAllVisibleItems')->willReturn([$item]);

        $checkoutSession = $this->createStub(Session::class);
        $checkoutSession->method('getLastRealOrder')->willReturn($order);

        $block = $this->createObjectToTest(checkoutSession: $checkoutSession);

        $result = json_decode($block->getOrderItemsConversionJson());
        $this->assertEquals('abc123', $result->{'42'}->queryID);
    }

    public function testToHtmlReturnsEmptyStringWhenConversionAnalyticsDisabled(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getStoreId')->willReturn(1);

        $checkoutSession = $this->createStub(Session::class);
        $checkoutSession->method('getLastRealOrder')->willReturn($order);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isClickConversionAnalyticsEnabled')->willReturn(false);

        $block = $this->createObjectToTest(checkoutSession: $checkoutSession, configHelper: $configHelper);

        $this->assertSame('', $block->toHtml());
    }

    public function testToHtmlReturnsEmptyStringWhenConversionModeIsNotPurchase(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getStoreId')->willReturn(1);

        $checkoutSession = $this->createStub(Session::class);
        $checkoutSession->method('getLastRealOrder')->willReturn($order);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isClickConversionAnalyticsEnabled')->willReturn(true);
        $configHelper->method('getConversionAnalyticsMode')->willReturn('click');

        $block = $this->createObjectToTest(checkoutSession: $checkoutSession, configHelper: $configHelper);

        $this->assertSame('', $block->toHtml());
    }
}
