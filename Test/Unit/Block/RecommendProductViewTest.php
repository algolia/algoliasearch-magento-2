<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block;

use Algolia\AlgoliaSearch\Block\RecommendProductView;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Registry\CurrentProduct;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\MockObject\MockObject;

class RecommendProductViewTest extends TestCase
{
    protected null|(RecommendProductView&MockObject) $block = null;
    protected null|(CurrentProduct&MockObject) $currentProduct = null;
    protected null|(ConfigHelper&MockObject) $configHelper = null;

    protected function setUp(): void
    {
        $this->currentProduct = $this->createMock(CurrentProduct::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);

        $this->block = $this->getMockBuilder(RecommendProductView::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->setPrivateProperty($this->block, 'currentProduct', $this->currentProduct);
        $this->setPrivateProperty($this->block, 'configHelper', $this->configHelper);
    }

    public function testGetProductReturnsProductFromRegistry(): void
    {
        $product = $this->createMock(Product::class);
        $this->currentProduct->method('get')->willReturn($product);

        $this->assertSame($product, $this->block->getProduct());
    }

    public function testGetProductCachesRegistryLookup(): void
    {
        $product = $this->createMock(Product::class);
        $this->currentProduct->expects($this->once())->method('get')->willReturn($product);

        $this->block->getProduct();
        $this->block->getProduct();
    }

    public function testGetAlgoliaRecommendConfigurationReturnsExpectedKeys(): void
    {
        $this->configHelper->method('isRecommendFrequentlyBroughtTogetherEnabled')->willReturn(true);
        $this->configHelper->method('isRecommendRelatedProductsEnabled')->willReturn(false);
        $this->configHelper->method('isTrendItemsEnabledInPDP')->willReturn(true);

        $config = $this->block->getAlgoliaRecommendConfiguration();

        $this->assertTrue($config['enabledFBT']);
        $this->assertFalse($config['enabledRelated']);
        $this->assertTrue($config['isTrendItemsEnabledInPDP']);
    }
}
