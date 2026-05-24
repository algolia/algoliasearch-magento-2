<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin\Cache;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\Product\CacheHelper;
use Algolia\AlgoliaSearch\Model\Cache\Product\IndexCollectionSize as Cache;
use Algolia\AlgoliaSearch\Plugin\Cache\CacheCleanProductPlugin;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use PHPUnit\Framework\MockObject\MockObject;

class CacheCleanProductPluginTest extends TestCase
{
    protected null|(Cache&MockObject) $cache = null;
    protected null|(ConfigHelper&MockObject) $configHelper = null;
    protected null|(CacheHelper&MockObject) $cacheHelper = null;
    protected null|(ProductResource&MockObject) $subject = null;
    protected null|(ProductResource&MockObject) $result = null;
    protected null|(CacheCleanProductPlugin&MockObject) $plugin = null;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(Cache::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->cacheHelper = $this->createMock(CacheHelper::class);
        $this->subject = $this->createMock(ProductResource::class);
        $this->result = $this->createMock(ProductResource::class);

        // Partial mock to isolate afterSave from the complex isEligibleNewProduct logic
        $this->plugin = $this->getMockBuilder(CacheCleanProductPlugin::class)
            ->setConstructorArgs([$this->cache, $this->configHelper, $this->cacheHelper])
            ->onlyMethods(['isEligibleNewProduct'])
            ->getMock();
        $this->plugin->method('isEligibleNewProduct')->willReturn(false);
    }

    private function makeProduct(string $sku, array $origData, array $newData): Product&MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn($sku);
        $product->method('getStoreId')->willReturn(1);
        $product->method('getOrigData')->willReturn($origData);
        $product->method('getData')->willReturn($newData);

        return $product;
    }

    public function testAfterSaveClearsCacheWhenStatusChanges(): void
    {
        $this->configHelper->method('includeNonVisibleProductsInIndex')->willReturn(true);
        $this->configHelper->method('getShowOutOfStock')->willReturn(true);

        $product = $this->makeProduct(
            'TEST-SKU',
            ['status' => Status::STATUS_DISABLED],
            ['status' => Status::STATUS_ENABLED]
        );

        $this->cache->expects($this->once())->method('clear')->with(1);

        $this->plugin->beforeSave($this->subject, $product);
        $result = $this->plugin->afterSave($this->subject, $this->result, $product);

        $this->assertSame($this->result, $result);
    }

    public function testAfterSaveDoesNotClearCacheWhenNothingChanges(): void
    {
        $this->configHelper->method('includeNonVisibleProductsInIndex')->willReturn(true);
        $this->configHelper->method('getShowOutOfStock')->willReturn(true);

        $data = ['status' => Status::STATUS_ENABLED];
        $product = $this->makeProduct('TEST-SKU', $data, $data);

        $this->cache->expects($this->never())->method('clear');

        $this->plugin->beforeSave($this->subject, $product);
        $this->plugin->afterSave($this->subject, $this->result, $product);
    }

    public function testAfterDeleteAlwaysClearsCache(): void
    {
        $this->cache->expects($this->once())->method('clear')->with(null);

        $result = $this->plugin->afterDelete($this->subject, $this->result);

        $this->assertSame($this->result, $result);
    }
}
