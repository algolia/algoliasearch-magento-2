<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block;

use Algolia\AlgoliaSearch\Block\RecommendProductView;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Registry\CurrentProduct;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\Template\Context;

class RecommendProductViewTest extends TestCase
{
    protected function createObjectToTest(
        ?CurrentProduct $currentProduct = null,
        ?ConfigHelper $configHelper = null,
    ): RecommendProductView {
        $context = $this->createStub(Context::class);

        return new RecommendProductView(
            $context,
            $currentProduct ?? $this->createStub(CurrentProduct::class),
            $configHelper ?? $this->createStub(ConfigHelper::class),
        );
    }

    public function testGetProductReturnsProductFromRegistry(): void
    {
        $product = $this->createStub(Product::class);

        $currentProduct = $this->createStub(CurrentProduct::class);
        $currentProduct->method('get')->willReturn($product);

        $block = $this->createObjectToTest(currentProduct: $currentProduct);

        $this->assertSame($product, $block->getProduct());
    }

    public function testGetProductCachesRegistryLookup(): void
    {
        $product = $this->createStub(Product::class);

        $currentProduct = $this->createMock(CurrentProduct::class);
        $currentProduct->expects($this->once())->method('get')->willReturn($product);

        $block = $this->createObjectToTest(currentProduct: $currentProduct);

        $block->getProduct();
        $block->getProduct();
    }

    public function testGetAlgoliaRecommendConfigurationReturnsExpectedKeys(): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isRecommendFrequentlyBroughtTogetherEnabled')->willReturn(true);
        $configHelper->method('isRecommendRelatedProductsEnabled')->willReturn(false);
        $configHelper->method('isTrendItemsEnabledInPDP')->willReturn(true);

        $block = $this->createObjectToTest(configHelper: $configHelper);

        $config = $block->getAlgoliaRecommendConfiguration();

        $this->assertTrue($config['enabledFBT']);
        $this->assertFalse($config['enabledRelated']);
        $this->assertTrue($config['isTrendItemsEnabledInPDP']);
    }
}
