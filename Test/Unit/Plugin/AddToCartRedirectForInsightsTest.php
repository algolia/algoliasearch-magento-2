<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Plugin\AddToCartRedirectForInsights;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AddToCartRedirectForInsightsTest extends TestCase
{
    protected null|(StoreManagerInterface&MockObject) $storeManager = null;
    protected null|(ProductRepositoryInterface&MockObject) $productRepository = null;
    protected null|(Session&MockObject) $checkoutSession = null;
    protected null|(StockRegistryInterface&MockObject) $stockRegistry = null;
    protected null|(ManagerInterface&MockObject) $eventManager = null;
    protected null|(ConfigHelper&MockObject) $configHelper = null;
    protected null|(Cart&MockObject) $cart = null;
    protected ?AddToCartRedirectForInsights $plugin = null;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->checkoutSession = $this->createMock(Session::class);
        $this->stockRegistry = $this->createMock(StockRegistryInterface::class);
        $this->eventManager = $this->createMock(ManagerInterface::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->cart = $this->createMock(Cart::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->plugin = new AddToCartRedirectForInsights(
            $this->storeManager,
            $this->productRepository,
            $this->checkoutSession,
            $this->stockRegistry,
            $this->eventManager,
            $this->configHelper
        );
    }

    public function testBeforeAddProductReturnsNullWhenInsightsDisabled(): void
    {
        $this->configHelper->method('isClickConversionAnalyticsEnabled')->with(1)->willReturn(false);

        $result = $this->plugin->beforeAddProduct(
            $this->cart,
            $this->createMock(Product::class),
            ['referer' => 'instantsearch', 'queryID' => 'abc', 'indexName' => 'idx']
        );

        $this->assertNull($result);
    }

    public function testBeforeAddProductReturnsNullWhenRequestInfoMissingInsightsKeys(): void
    {
        $this->configHelper->method('isClickConversionAnalyticsEnabled')->willReturn(true);

        $result = $this->plugin->beforeAddProduct(
            $this->cart,
            1,
            ['referer' => 'instantsearch'] // missing queryID and indexName
        );

        $this->assertNull($result);
    }

    public function testBeforeAddProductReturnsNullWhenRefererIsNotInstantSearch(): void
    {
        $this->configHelper->method('isClickConversionAnalyticsEnabled')->willReturn(true);

        $result = $this->plugin->beforeAddProduct(
            $this->cart,
            1,
            ['referer' => 'catalog', 'queryID' => 'abc123', 'indexName' => 'products']
        );

        $this->assertNull($result);
    }
}
