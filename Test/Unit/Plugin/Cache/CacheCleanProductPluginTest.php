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
    /**
     * Partial mock to isolate afterSave from the complex isEligibleNewProduct logic.
     */
    protected function createObjectToTest(
        ?Cache $cache = null,
        ?ConfigHelper $configHelper = null,
        ?CacheHelper $cacheHelper = null,
    ): CacheCleanProductPlugin&MockObject {
        return $this->getMockBuilder(CacheCleanProductPlugin::class)
            ->setConstructorArgs([
                $cache ?? $this->createStub(Cache::class),
                $configHelper ?? $this->createStub(ConfigHelper::class),
                $cacheHelper ?? $this->createStub(CacheHelper::class),
            ])
            ->onlyMethods(['isEligibleNewProduct'])
            ->getMock();
    }

    private function createProductStub(string $sku, array $origData, array $newData): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getSku')->willReturn($sku);
        $product->method('getStoreId')->willReturn(1);
        $product->method('getOrigData')->willReturn($origData);
        $product->method('getData')->willReturn($newData);

        return $product;
    }

    public function testAfterSaveClearsCacheWhenStatusChanges(): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('includeNonVisibleProductsInIndex')->willReturn(true);
        $configHelper->method('getShowOutOfStock')->willReturn(true);

        $cache = $this->createMock(Cache::class);
        $cache->expects($this->once())->method('clear')->with(1);

        $plugin = $this->createObjectToTest($cache, $configHelper);
        // isEligibleNewProduct() is the first term of the OR chain, so it's always evaluated
        // exactly once when afterSave() has non-empty original data to compare against.
        $plugin->expects($this->once())->method('isEligibleNewProduct')->willReturn(false);

        $product = $this->createProductStub(
            'TEST-SKU',
            ['status' => Status::STATUS_DISABLED],
            ['status' => Status::STATUS_ENABLED]
        );

        $subject = $this->createStub(ProductResource::class);
        $result = $this->createStub(ProductResource::class);

        $plugin->beforeSave($subject, $product);
        $returnValue = $plugin->afterSave($subject, $result, $product);

        $this->assertSame($result, $returnValue);
    }

    public function testAfterSaveDoesNotClearCacheWhenNothingChanges(): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('includeNonVisibleProductsInIndex')->willReturn(true);
        $configHelper->method('getShowOutOfStock')->willReturn(true);

        $cache = $this->createMock(Cache::class);
        $cache->expects($this->never())->method('clear');

        $plugin = $this->createObjectToTest($cache, $configHelper);
        $plugin->expects($this->once())->method('isEligibleNewProduct')->willReturn(false);

        $data = ['status' => Status::STATUS_ENABLED];
        $product = $this->createProductStub('TEST-SKU', $data, $data);

        $subject = $this->createStub(ProductResource::class);
        $result = $this->createStub(ProductResource::class);

        $plugin->beforeSave($subject, $product);
        $plugin->afterSave($subject, $result, $product);
    }

    public function testAfterDeleteAlwaysClearsCache(): void
    {
        $cache = $this->createMock(Cache::class);
        $cache->expects($this->once())->method('clear')->with(null);

        $plugin = $this->createObjectToTest($cache);
        // afterDelete() never reaches afterSave()'s OR chain.
        $plugin->expects($this->never())->method('isEligibleNewProduct');

        $subject = $this->createStub(ProductResource::class);
        $result = $this->createStub(ProductResource::class);

        $returnValue = $plugin->afterDelete($subject, $result);

        $this->assertSame($result, $returnValue);
    }
}
