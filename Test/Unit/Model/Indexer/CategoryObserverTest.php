<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Indexer;

use Algolia\AlgoliaSearch\Model\Indexer\CategoryObserver;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Product\BatchQueueProcessor as ProductBatchQueueProcessor;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Mview\View\ChangelogInterface;
use Magento\Framework\Mview\ViewInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class CategoryObserverTest extends TestCase
{
    protected null|(IndexerInterface&MockObject) $categoryIndexer = null;
    protected null|(IndexerInterface&MockObject) $productIndexer = null;
    protected null|(IndexerRegistry&MockObject) $indexerRegistry = null;
    protected null|(StoreManagerInterface&MockObject) $storeManager = null;
    protected null|(ResourceConnection&MockObject) $resource = null;
    protected null|(ProductBatchQueueProcessor&MockObject) $productBatchQueueProcessor = null;
    protected null|(AlgoliaCredentialsManager&MockObject) $algoliaCredentialsManager = null;
    protected ?CategoryObserver $observer = null;
    protected null|(CategoryResourceModel&MockObject) $categoryResource = null;
    protected null|(CategoryResourceModel&MockObject) $result = null;
    protected null|(CategoryModel&MockObject) $category = null;

    protected function setUp(): void
    {
        $this->categoryIndexer = $this->createMock(IndexerInterface::class);
        $this->productIndexer = $this->createMock(IndexerInterface::class);
        $this->indexerRegistry = $this->createMock(IndexerRegistry::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->resource = $this->createMock(ResourceConnection::class);
        $this->productBatchQueueProcessor = $this->createMock(ProductBatchQueueProcessor::class);
        $this->algoliaCredentialsManager = $this->createMock(AlgoliaCredentialsManager::class);

        $this->indexerRegistry->method('get')
            ->willReturnMap([
                ['algolia_categories', $this->categoryIndexer],
                ['algolia_products', $this->productIndexer],
            ]);

        $this->observer = new CategoryObserver(
            $this->indexerRegistry,
            $this->storeManager,
            $this->resource,
            $this->productBatchQueueProcessor,
            $this->algoliaCredentialsManager
        );

        $this->categoryResource = $this->createMock(CategoryResourceModel::class);
        $this->result = $this->createMock(CategoryResourceModel::class);
        // getChangedProductIds() is a @method docblock annotation (magic method), not a real PHP method.
        // addMethods() is required so PHPUnit 10 allows it to be configured alongside real methods.
        $this->category = $this->getMockBuilder(CategoryModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getOrigData', 'getData', 'getProductCollection', 'getProductsPosition'])
            ->addMethods(['getChangedProductIds'])
            ->getMock();
    }

    // -------------------------------------------------------------------------
    // afterSave
    // -------------------------------------------------------------------------

    public function testAfterSaveReturnsEarlyWhenCredentialsInvalid(): void
    {
        $this->algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(false);

        $this->categoryResource->expects($this->never())->method('addCommitCallback');

        $returnValue = $this->observer->afterSave($this->categoryResource, $this->result, $this->category);

        $this->assertSame($this->result, $returnValue);
    }

    public function testAfterSaveReturnsResultWhenCredentialsValid(): void
    {
        $this->algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);
        $this->categoryResource->method('addCommitCallback');

        $returnValue = $this->observer->afterSave($this->categoryResource, $this->result, $this->category);

        $this->assertSame($this->result, $returnValue);
    }

    public function testAfterSaveCallbackReindexesCategoryRowWhenNotScheduled(): void
    {
        $categoryId = 42;
        $this->algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);
        $this->category->method('getId')->willReturn($categoryId);
        $this->category->method('getChangedProductIds')->willReturn(null);
        $this->category->method('getOrigData')->willReturn('same');
        $this->category->method('getData')->willReturn('same');
        $this->category->method('getProductCollection')->willReturn($this->createMockProductCollection([]));
        $this->categoryIndexer->method('isScheduled')->willReturn(false);

        $this->categoryIndexer->expects($this->once())->method('reindexRow')->with($categoryId);

        $this->captureAndInvokeCommitCallback('afterSave', $this->category);
    }

    public function testAfterSaveCallbackSkipsProductReindexWhenNoAttributeChangesAndNoChangedProducts(): void
    {
        $this->algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);
        $this->category->method('getId')->willReturn(1);
        $this->category->method('getChangedProductIds')->willReturn([]);
        // origData === getData for all watched keys → no collectionIds
        $this->category->method('getOrigData')->willReturn('same');
        $this->category->method('getData')->willReturn('same');
        $this->category->method('getProductCollection')->willReturn($this->createMockProductCollection([]));
        $this->categoryIndexer->method('isScheduled')->willReturn(false);

        $this->productBatchQueueProcessor->expects($this->never())->method('processBatch');

        $this->captureAndInvokeCommitCallback('afterSave', $this->category);
    }

    public function testAfterSaveCallbackReindexesProductsWhenNameChanges(): void
    {
        $productIds = [10, 20, 30];
        $store = $this->createMockStore(1);
        $this->storeManager->method('getStores')->willReturn([1 => $store]);

        $this->algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);
        $this->category->method('getId')->willReturn(1);
        $this->category->method('getChangedProductIds')->willReturn([]);
        $this->category->method('getProductCollection')->willReturn($this->createMockProductCollection($productIds));
        $this->categoryIndexer->method('isScheduled')->willReturn(false);

        $this->category->method('getOrigData')->willReturnCallback(
            fn($key) => $key === 'name' ? 'Old Name' : null
        );
        $this->category->method('getData')->willReturnCallback(
            fn($key) => $key === 'name' ? 'New Name' : null
        );

        $this->productBatchQueueProcessor->expects($this->once())
            ->method('processBatch')
            ->with(1, $productIds);

        $this->captureAndInvokeCommitCallback('afterSave', $this->category);
    }

    public function testAfterSaveCallbackMergesAndDeduplicatesChangedAndCollectionProductIds(): void
    {
        $changedProductIds = [5, 6];
        $collectionIds = [6, 7, 8]; // 6 appears in both
        $store = $this->createMockStore(1);
        $this->storeManager->method('getStores')->willReturn([1 => $store]);

        $this->algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);
        $this->category->method('getId')->willReturn(1);
        $this->category->method('getChangedProductIds')->willReturn($changedProductIds);
        $this->category->method('getProductCollection')->willReturn($this->createMockProductCollection($collectionIds));
        $this->categoryIndexer->method('isScheduled')->willReturn(false);

        $this->category->method('getOrigData')->willReturnCallback(fn($k) => $k === 'name' ? 'Old' : null);
        $this->category->method('getData')->willReturnCallback(fn($k) => $k === 'name' ? 'New' : null);

        $this->productBatchQueueProcessor->expects($this->once())
            ->method('processBatch')
            ->with(1, $this->callback(function (array $ids) {
                sort($ids);
                $this->assertEquals([5, 6, 7, 8], $ids);
                return true;
            }));

        $this->captureAndInvokeCommitCallback('afterSave', $this->category);
    }

    public function testAfterSaveCallbackUpdatesChangelogWhenScheduledAndHasCollectionIdsButNoChangedProducts(): void
    {
        $collectionIds = [10, 20];
        $changelogTableName = 'algolia_products_cl';

        $this->algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);
        $this->category->method('getId')->willReturn(1);
        $this->category->method('getChangedProductIds')->willReturn([]);
        $this->category->method('getProductCollection')->willReturn($this->createMockProductCollection($collectionIds));
        $this->categoryIndexer->method('isScheduled')->willReturn(true);

        // Attribute change to populate collectionIds
        $this->category->method('getOrigData')->willReturnCallback(fn($k) => $k === 'name' ? 'Old' : null);
        $this->category->method('getData')->willReturnCallback(fn($k) => $k === 'name' ? 'New' : null);

        $this->productIndexer->method('isScheduled')->willReturn(true);

        $changelog = $this->createMock(ChangelogInterface::class);
        $changelog->method('getName')->willReturn('algolia_products_cl');
        $view = $this->createMock(ViewInterface::class);
        $view->method('getChangelog')->willReturn($changelog);
        $this->productIndexer->method('getView')->willReturn($view);

        $connection = $this->createMock(AdapterInterface::class);
        $this->resource->method('getTableName')->with('algolia_products_cl')->willReturn($changelogTableName);
        $this->resource->method('getConnection')->willReturn($connection);
        $connection->method('isTableExists')->with($changelogTableName)->willReturn(true);

        $connection->expects($this->once())
            ->method('insertMultiple')
            ->with($changelogTableName, [
                ['entity_id' => 10],
                ['entity_id' => 20],
            ]);

        $this->captureAndInvokeCommitCallback('afterSave', $this->category);
    }

    public function testAfterSaveCallbackCallsReindexListWhenScheduledAndProductIndexerNotScheduled(): void
    {
        $collectionIds = [10, 20];

        $this->algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);
        $this->category->method('getId')->willReturn(1);
        $this->category->method('getChangedProductIds')->willReturn([]);
        $this->category->method('getProductCollection')->willReturn($this->createMockProductCollection($collectionIds));
        $this->categoryIndexer->method('isScheduled')->willReturn(true);

        // Attribute change to populate collectionIds
        $this->category->method('getOrigData')->willReturnCallback(fn($k) => $k === 'name' ? 'Old' : null);
        $this->category->method('getData')->willReturnCallback(fn($k) => $k === 'name' ? 'New' : null);

        $this->productIndexer->method('isScheduled')->willReturn(false);
        $this->productIndexer->expects($this->once())->method('reindexList')->with($collectionIds);

        $this->captureAndInvokeCommitCallback('afterSave', $this->category);
    }

    public function testAfterSaveCallbackSkipsInsertWhenChangelogTableDoesNotExist(): void
    {
        $collectionIds = [10, 20];

        $this->algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);
        $this->category->method('getId')->willReturn(1);
        $this->category->method('getChangedProductIds')->willReturn([]);
        $this->category->method('getProductCollection')->willReturn($this->createMockProductCollection($collectionIds));
        $this->categoryIndexer->method('isScheduled')->willReturn(true);

        $this->category->method('getOrigData')->willReturnCallback(fn($k) => $k === 'name' ? 'Old' : null);
        $this->category->method('getData')->willReturnCallback(fn($k) => $k === 'name' ? 'New' : null);

        $this->productIndexer->method('isScheduled')->willReturn(true);

        $changelog = $this->createMock(ChangelogInterface::class);
        $changelog->method('getName')->willReturn('algolia_products_cl');
        $view = $this->createMock(ViewInterface::class);
        $view->method('getChangelog')->willReturn($changelog);
        $this->productIndexer->method('getView')->willReturn($view);

        $connection = $this->createMock(AdapterInterface::class);
        $this->resource->method('getTableName')->willReturn('algolia_products_cl');
        $this->resource->method('getConnection')->willReturn($connection);
        $connection->method('isTableExists')->willReturn(false);

        $connection->expects($this->never())->method('insertMultiple');

        $this->captureAndInvokeCommitCallback('afterSave', $this->category);
    }

    public function testAfterSaveCallbackSkipsUpdateCategoryProductsWhenScheduledAndChangedProductsExist(): void
    {
        $this->algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);
        $this->category->method('getId')->willReturn(1);
        $this->category->method('getChangedProductIds')->willReturn([10, 20]);
        // No attribute changes, so collectionIds stays empty
        $this->category->method('getOrigData')->willReturn('same');
        $this->category->method('getData')->willReturn('same');
        $this->category->method('getProductCollection')->willReturn($this->createMockProductCollection([]));
        $this->categoryIndexer->method('isScheduled')->willReturn(true);

        // updateCategoryProducts should not be triggered (changedProductIds count > 0)
        $this->productIndexer->expects($this->never())->method('reindexList');
        $this->resource->expects($this->never())->method('getConnection');

        $this->captureAndInvokeCommitCallback('afterSave', $this->category);
    }

    // -------------------------------------------------------------------------
    // afterDelete
    // -------------------------------------------------------------------------

    public function testAfterDeleteReturnsResult(): void
    {
        $this->categoryResource->method('addCommitCallback');

        $returnValue = $this->observer->afterDelete($this->categoryResource, $this->result, $this->category);

        $this->assertSame($this->result, $returnValue);
    }

    public function testAfterDeleteCallbackReindexesCategoryAndProductsWhenNotScheduled(): void
    {
        $categoryId = 15;
        $productPositions = [10 => 0, 20 => 1, 30 => 2];
        $store = $this->createMockStore(1);
        $this->storeManager->method('getStores')->willReturn([1 => $store]);

        $this->category->method('getId')->willReturn($categoryId);
        $this->category->method('getProductsPosition')->willReturn($productPositions);
        $this->categoryIndexer->method('isScheduled')->willReturn(false);

        $this->categoryIndexer->expects($this->once())->method('reindexRow')->with($categoryId);
        $this->productBatchQueueProcessor->expects($this->once())
            ->method('processBatch')
            ->with(1, array_keys($productPositions));

        $this->captureAndInvokeCommitCallback('afterDelete', $this->category);
    }

    public function testAfterDeleteCallbackSkipsReindexWhenScheduled(): void
    {
        $this->category->method('getId')->willReturn(1);
        $this->categoryIndexer->method('isScheduled')->willReturn(true);

        $this->categoryIndexer->expects($this->never())->method('reindexRow');
        $this->productBatchQueueProcessor->expects($this->never())->method('processBatch');

        $this->captureAndInvokeCommitCallback('afterDelete', $this->category);
    }

    // -------------------------------------------------------------------------
    // reindexAffectedProducts (protected — tested via invokeMethod)
    // -------------------------------------------------------------------------

    public function testReindexAffectedProductsSkipsWhenEmpty(): void
    {
        $this->storeManager->expects($this->never())->method('getStores');
        $this->productBatchQueueProcessor->expects($this->never())->method('processBatch');

        $this->invokeMethod($this->observer, 'reindexAffectedProducts', [[]]);
    }

    public function testReindexAffectedProductsCallsProcessBatchForEachStore(): void
    {
        $productIds = [1, 2, 3];
        $store1 = $this->createMockStore(1);
        $store2 = $this->createMockStore(2);
        $this->storeManager->method('getStores')->willReturn([1 => $store1, 2 => $store2]);

        $this->productBatchQueueProcessor->expects($this->exactly(2))
            ->method('processBatch')
            ->willReturnCallback(function (int $storeId, array $ids) use ($productIds) {
                $this->assertContains($storeId, [1, 2]);
                $this->assertEquals($productIds, $ids);
            });

        $this->invokeMethod($this->observer, 'reindexAffectedProducts', [$productIds]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createMockProductCollection(array $productIds): ProductCollection
    {
        $collection = $this->createMock(ProductCollection::class);
        $collection->method('getColumnValues')->with('entity_id')->willReturn($productIds);
        return $collection;
    }

    private function createMockStore(int $storeId): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        return $store;
    }

    /**
     * Captures the commit callback registered via addCommitCallback and immediately invokes it.
     */
    private function captureAndInvokeCommitCallback(string $method, CategoryModel $category): void
    {
        $capturedCallback = null;
        $this->categoryResource->method('addCommitCallback')
            ->willReturnCallback(function (callable $callback) use (&$capturedCallback) {
                $capturedCallback = $callback;
            });

        $this->observer->$method($this->categoryResource, $this->result, $category);

        $this->assertNotNull($capturedCallback, 'No commit callback was registered by ' . $method);
        $capturedCallback();
    }
}
