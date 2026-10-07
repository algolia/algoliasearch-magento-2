<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Data;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Data\PricingContext;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;

class PricingContextTest extends TestCase
{
    protected function createObjectToTest(
        ?ConfigHelper $configHelper = null
    ): PricingContext {
        return new PricingContext(
            $configHelper ?? $this->createStub(ConfigHelper::class)
        );
    }

    public function testSetAndGetProductStoresAndRetrievesProduct(): void
    {
        $pricingContext = $this->createObjectToTest();
        $product = $this->createStub(Product::class);

        $pricingContext->setProduct($product);

        $this->assertSame($product, $pricingContext->getProduct());
    }

    public function testSetAndGetSubProductsStoresAndRetrievesArray(): void
    {
        $pricingContext = $this->createObjectToTest();
        $subProducts = [
            $this->createStub(Product::class),
            $this->createStub(Product::class),
        ];

        $pricingContext->setSubProducts($subProducts);

        $this->assertSame($subProducts, $pricingContext->getSubProducts());
    }

    public function testSetAndGetCurrencyCodeStoresAndRetrievesValue(): void
    {
        $pricingContext = $this->createObjectToTest();

        $pricingContext->setCurrencyCode('EUR');

        $this->assertSame('EUR', $pricingContext->getCurrencyCode());
    }

    public function testSetAndShouldIncludeTaxStoresAndRetrievesValue(): void
    {
        $pricingContext = $this->createObjectToTest();

        $pricingContext->setShouldIncludeTax(true);

        $this->assertTrue($pricingContext->shouldIncludeTax());
    }

    public function testShouldIncludeTaxReturnsFalseWhenSetToFalse(): void
    {
        $pricingContext = $this->createObjectToTest();

        $pricingContext->setShouldIncludeTax(false);

        $this->assertFalse($pricingContext->shouldIncludeTax());
    }

    public function testGetStoreReturnsStoreFromProduct(): void
    {
        $pricingContext = $this->createObjectToTest();
        $store = $this->createStub(Store::class);
        $product = $this->createStub(Product::class);
        $product->method('getStore')->willReturn($store);

        $pricingContext->setProduct($product);

        $this->assertSame($store, $pricingContext->getStore());
    }

    public function testGetBaseCurrencyCodeReturnsBaseCurrencyFromStore(): void
    {
        $pricingContext = $this->createObjectToTest();
        $store = $this->createStub(Store::class);
        $store->method('getBaseCurrencyCode')->willReturn('USD');
        $product = $this->createStub(Product::class);
        $product->method('getStore')->willReturn($store);

        $pricingContext->setProduct($product);

        $this->assertSame('USD', $pricingContext->getBaseCurrencyCode());
    }

    public function testIsCurrencyDifferentFromBaseReturnsTrueWhenCurrenciesDiffer(): void
    {
        $pricingContext = $this->createObjectToTest();
        $store = $this->createStub(Store::class);
        $store->method('getBaseCurrencyCode')->willReturn('USD');
        $product = $this->createStub(Product::class);
        $product->method('getStore')->willReturn($store);

        $pricingContext->setProduct($product);
        $pricingContext->setCurrencyCode('EUR');

        $this->assertTrue($pricingContext->isCurrencyDifferentFromBase());
    }

    public function testIsCurrencyDifferentFromBaseReturnsFalseWhenCurrenciesMatch(): void
    {
        $pricingContext = $this->createObjectToTest();
        $store = $this->createStub(Store::class);
        $store->method('getBaseCurrencyCode')->willReturn('USD');
        $product = $this->createStub(Product::class);
        $product->method('getStore')->willReturn($store);

        $pricingContext->setProduct($product);
        $pricingContext->setCurrencyCode('USD');

        $this->assertFalse($pricingContext->isCurrencyDifferentFromBase());
    }

    public function testAreCustomerGroupsEnabledDelegatesToConfigHelperWithStoreId(): void
    {
        $storeId = 5;
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn($storeId);
        $product = $this->createStub(Product::class);
        $product->method('getStore')->willReturn($store);

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        $pricingContext = $this->createObjectToTest($configHelper);
        $pricingContext->setProduct($product);

        $result = $pricingContext->areCustomerGroupsEnabled();

        $this->assertTrue($result);
    }

    public function testAreCustomerGroupsEnabledReturnsFalseWhenDisabled(): void
    {
        $storeId = 3;
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn($storeId);
        $product = $this->createStub(Product::class);
        $product->method('getStore')->willReturn($store);

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(false);

        $pricingContext = $this->createObjectToTest($configHelper);
        $pricingContext->setProduct($product);

        $result = $pricingContext->areCustomerGroupsEnabled();

        $this->assertFalse($result);
    }

    public function testIsFptEnabledDelegatesToConfigHelperWithStoreId(): void
    {
        $storeId = 7;
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn($storeId);
        $product = $this->createStub(Product::class);
        $product->method('getStore')->willReturn($store);

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('isFptEnabled')
            ->with($storeId)
            ->willReturn(true);

        $pricingContext = $this->createObjectToTest($configHelper);
        $pricingContext->setProduct($product);

        $result = $pricingContext->isFptEnabled();

        $this->assertTrue($result);
    }

    public function testIsFptEnabledReturnsFalseWhenDisabled(): void
    {
        $storeId = 2;
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn($storeId);
        $product = $this->createStub(Product::class);
        $product->method('getStore')->willReturn($store);

        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->expects($this->once())
            ->method('isFptEnabled')
            ->with($storeId)
            ->willReturn(false);

        $pricingContext = $this->createObjectToTest($configHelper);
        $pricingContext->setProduct($product);

        $result = $pricingContext->isFptEnabled();

        $this->assertFalse($result);
    }
}
