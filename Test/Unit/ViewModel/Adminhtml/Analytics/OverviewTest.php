<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\ViewModel\Adminhtml\Analytics;

use Algolia\AlgoliaSearch\DataProvider\Analytics\IndexEntityDataProvider;
use Algolia\AlgoliaSearch\Helper\AnalyticsHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\AlgoliaSearch\ViewModel\Adminhtml\Analytics\Overview;
use Algolia\AlgoliaSearch\ViewModel\Adminhtml\BackendView;
use Magento\Framework\DataObject;
use Magento\Framework\Data\Collection;
use Magento\Store\Api\Data\StoreInterface;

class OverviewTest extends TestCase
{
    private const HITS = [
        ['hit' => '1', 'count' => 10],
        ['hit' => '2', 'count' => 5],
    ];

    private function createObjectToTest(
        string $type,
        IndexEntityDataProvider $dataProvider,
        ?BackendView $backendView = null
    ): Overview {
        $analyticsHelper = $this->createStub(AnalyticsHelper::class);
        $analyticsHelper->method('getTopHits')->willReturn(['hits' => self::HITS]);

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);

        $overview = $this->getMockBuilder(Overview::class)
            ->setConstructorArgs([
                $backendView ?? $this->createStub(BackendView::class),
                $analyticsHelper,
                $dataProvider,
            ])
            ->onlyMethods(['getCurrentType', 'getStore', 'getAnalyticsParams'])
            ->getMock();
        $overview->method('getCurrentType')->willReturn($type);
        $overview->method('getStore')->willReturn($store);
        $overview->method('getAnalyticsParams')->willReturn([]);

        return $overview;
    }

    private function collectionWithItems(array $itemsById): Collection
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getItemById')->willReturnCallback(fn($id) => $itemsById[$id] ?? null);
        $collection->method('getItemByColumnValue')
            ->willReturnCallback(fn($column, $value) => $itemsById[$value] ?? null);

        return $collection;
    }

    public function testPopularProductsKeepHitWhenProductWasDeleted(): void
    {
        $product = new class (['name' => 'Existing product']) extends DataObject {
            public function getProductUrl(bool $useSid = true): string
            {
                return 'https://example.com/existing.html';
            }
        };

        $dataProvider = $this->createStub(IndexEntityDataProvider::class);
        $dataProvider->method('getProductCollection')->willReturn($this->collectionWithItems(['1' => $product]));

        $results = $this->createObjectToTest('products', $dataProvider)->getPopularResults();

        $this->assertCount(2, $results);
        $this->assertSame('Existing product', $results[0]['name']);
        $this->assertSame('https://example.com/existing.html', $results[0]['url']);
        $this->assertArrayNotHasKey('name', $results[1]);
        $this->assertArrayNotHasKey('url', $results[1]);
        $this->assertSame(5, $results[1]['count']);
    }

    public function testPopularCategoriesKeepHitWhenCategoryWasDeleted(): void
    {
        $category = new class (['name' => 'Existing category']) extends DataObject {
            public function getUrl(): string
            {
                return 'https://example.com/category.html';
            }
        };

        $dataProvider = $this->createStub(IndexEntityDataProvider::class);
        $dataProvider->method('getCategoryCollection')->willReturn($this->collectionWithItems(['1' => $category]));

        $results = $this->createObjectToTest('categories', $dataProvider)->getPopularResults();

        $this->assertCount(2, $results);
        $this->assertSame('Existing category', $results[0]['name']);
        $this->assertArrayNotHasKey('name', $results[1]);
        $this->assertArrayNotHasKey('url', $results[1]);
    }

    public function testPopularPagesKeepHitWhenPageWasDeleted(): void
    {
        $page = new DataObject(['title' => 'Existing page', 'identifier' => 'existing-page']);

        $dataProvider = $this->createStub(IndexEntityDataProvider::class);
        $dataProvider->method('getPageCollection')->willReturn($this->collectionWithItems(['1' => $page]));

        $urlBuilder = $this->createStub(\Magento\Framework\UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturn('https://example.com/existing-page');
        $backendView = $this->createStub(BackendView::class);
        $backendView->method('getUrlInterface')->willReturn($urlBuilder);

        $overview = $this->createObjectToTest('pages', $dataProvider, $backendView);
        $results = $overview->getPopularResults();

        $this->assertCount(2, $results);
        $this->assertSame('Existing page', $results[0]['name']);
        $this->assertArrayNotHasKey('name', $results[1]);
        $this->assertArrayNotHasKey('url', $results[1]);
    }
}
