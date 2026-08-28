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
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class AddToCartRedirectForInsightsTest extends TestCase
{
    protected function createObjectToTest(?ConfigHelper $configHelper = null): AddToCartRedirectForInsights
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new AddToCartRedirectForInsights(
            $storeManager,
            $this->createStub(ProductRepositoryInterface::class),
            $this->createStub(Session::class),
            $this->createStub(StockRegistryInterface::class),
            $this->createStub(ManagerInterface::class),
            $configHelper ?? $this->createStub(ConfigHelper::class),
        );
    }

    public function testBeforeAddProductReturnsNullWhenInsightsDisabled(): void
    {
        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->method('isClickConversionAnalyticsEnabled')->with(1)->willReturn(false);

        $plugin = $this->createObjectToTest($configHelper);

        $result = $plugin->beforeAddProduct(
            $this->createStub(Cart::class),
            $this->createStub(Product::class),
            ['referer' => 'instantsearch', 'queryID' => 'abc', 'indexName' => 'idx']
        );

        $this->assertNull($result);
    }

    public function testBeforeAddProductReturnsNullWhenRequestInfoMissingInsightsKeys(): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isClickConversionAnalyticsEnabled')->willReturn(true);

        $plugin = $this->createObjectToTest($configHelper);

        $result = $plugin->beforeAddProduct(
            $this->createStub(Cart::class),
            1,
            ['referer' => 'instantsearch'] // missing queryID and indexName
        );

        $this->assertNull($result);
    }

    public function testBeforeAddProductReturnsNullWhenRefererIsNotInstantSearch(): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isClickConversionAnalyticsEnabled')->willReturn(true);

        $plugin = $this->createObjectToTest($configHelper);

        $result = $plugin->beforeAddProduct(
            $this->createStub(Cart::class),
            1,
            ['referer' => 'catalog', 'queryID' => 'abc123', 'indexName' => 'products']
        );

        $this->assertNull($result);
    }
}
