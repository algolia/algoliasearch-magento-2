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
    protected function createObjectToTest(
        ?IndexerInterface $categoryIndexer = null,
        ?IndexerInterface $productIndexer = null,
        ?StoreManagerInterface $storeManager = null,
        ?ResourceConnection $resource = null,
        ?ProductBatchQueueProcessor $productBatchQueueProcessor = null,
        ?AlgoliaCredentialsManager $algoliaCredentialsManager = null,
    ): CategoryObserver {
        $categoryIndexer ??= $this->createStub(IndexerInterface::class);
        $productIndexer ??= $this->createStub(IndexerInterface::class);

        $indexerRegistry = $this->createStub(IndexerRegistry::class);
        $indexerRegistry->method('get')->willReturnMap([
            ['algolia_categories', $categoryIndexer],
            ['algolia_products', $productIndexer],
        ]);

        return new CategoryObserver(
            $indexerRegistry,
            $storeManager ?? $this->createStub(StoreManagerInterface::class),
            $resource ?? $this->createStub(ResourceConnection::class),
            $productBatchQueueProcessor ?? $this->createStub(ProductBatchQueueProcessor::class),
            $algoliaCredentialsManager ?? $this->createStub(AlgoliaCredentialsManager::class),
        );
    }

    /**
     * getChangedProductIds() is a @method docblock annotation (magic method), not a real PHP
     * method — __call must be included in onlyMethods so it can be configured in PHPUnit 12.
     */
    private function createCategoryMock(): CategoryModel&MockObject
    {
        return $this->getMockBuilder(CategoryModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getOrigData', 'getData', 'getProductCollection', 'getProductsPosition', '__call'])
            ->getMock();
    }

    private function createProductCollectionStub(array $productIds): ProductCollection
    {
        $collection = $this->createMock(ProductCollection::class);
        $collection->method('getColumnValues')->with('entity_id')->willReturn($productIds);

        return $collection;
    }

    private function createStoreStub(int $storeId): StoreInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);

        return $store;
    }

    /**
     * Captures the commit callback registered via addCommitCallback and immediately invokes it.
     */
    private function captureAndInvokeCommitCallback(CategoryObserver $observer, string $method, CategoryModel $category): void
    {
        $capturedCallback = null;
        $categoryResource = $this->createStub(CategoryResourceModel::class);
        $categoryResource->method('addCommitCallback')
            ->willReturnCallback(function (callable $callback) use (&$capturedCallback) {
                $capturedCallback = $callback;
            });

        $result = $this->createStub(CategoryResourceModel::class);

        $observer->$method($categoryResource, $result, $category);

        $this->assertNotNull($capturedCallback, 'No commit callback was registered by ' . $method);
        $capturedCallback();
    }

    // -------------------------------------------------------------------------
    // afterSave
    // -------------------------------------------------------------------------

    public function testAfterSaveReturnsEarlyWhenCredentialsInvalid(): void
    {
        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(false);

        $categoryResource = $this->createMock(CategoryResourceModel::class);
        $categoryResource->expects($this->never())->method('addCommitCallback');

        $result = $this->createStub(CategoryResourceModel::class);

        // The credentials check short-circuits before the callback ever touches the category.
        $category = $this->createCategoryMock();
        $category->expects($this->never())->method('getId');

        $observer = $this->createObjectToTest(algoliaCredentialsManager: $algoliaCredentialsManager);

        $returnValue = $observer->afterSave($categoryResource, $result, $category);

        $this->assertSame($result, $returnValue);
    }

    public function testAfterSaveReturnsResultWhenCredentialsValid(): void
    {
        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        $categoryResource = $this->createStub(CategoryResourceModel::class);
        $categoryResource->method('addCommitCallback');

        $result = $this->createStub(CategoryResourceModel::class);

        // The callback is registered but never invoked in this test, so the category is untouched.
        $category = $this->createCategoryMock();
        $category->expects($this->never())->method('getId');

        $observer = $this->createObjectToTest(algoliaCredentialsManager: $algoliaCredentialsManager);

        $returnValue = $observer->afterSave($categoryResource, $result, $category);

        $this->assertSame($result, $returnValue);
    }

    public function testAfterSaveCallbackReindexesCategoryRowWhenNotScheduled(): void
    {
        $categoryId = 42;

        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        $category = $this->createCategoryMock();
        // Not scheduled → reindexRow(category->getId()) is called exactly once.
        $category->expects($this->once())->method('getId')->willReturn($categoryId);
        $category->method('__call')
            ->willReturnCallback(fn($name, $args) => $name === 'getChangedProductIds' ? null : null);
        $category->method('getOrigData')->willReturn('same');
        $category->method('getData')->willReturn('same');
        $category->method('getProductCollection')->willReturn($this->createProductCollectionStub([]));

        $categoryIndexer = $this->createMock(IndexerInterface::class);
        $categoryIndexer->method('isScheduled')->willReturn(false);
        $categoryIndexer->expects($this->once())->method('reindexRow')->with($categoryId);

        $observer = $this->createObjectToTest(
            algoliaCredentialsManager: $algoliaCredentialsManager,
            categoryIndexer: $categoryIndexer,
        );

        $this->captureAndInvokeCommitCallback($observer, 'afterSave', $category);
    }

    public function testAfterSaveCallbackSkipsProductReindexWhenNoAttributeChangesAndNoChangedProducts(): void
    {
        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        $category = $this->createCategoryMock();
        $category->expects($this->once())->method('getId')->willReturn(1);
        $category->method('__call')
            ->willReturnCallback(fn($name, $args) => $name === 'getChangedProductIds' ? [] : null);
        // origData === getData for all watched keys → no collectionIds
        $category->method('getOrigData')->willReturn('same');
        $category->method('getData')->willReturn('same');
        $category->method('getProductCollection')->willReturn($this->createProductCollectionStub([]));

        $categoryIndexer = $this->createStub(IndexerInterface::class);
        $categoryIndexer->method('isScheduled')->willReturn(false);

        $productBatchQueueProcessor = $this->createMock(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->expects($this->never())->method('processBatch');

        $observer = $this->createObjectToTest(
            algoliaCredentialsManager: $algoliaCredentialsManager,
            categoryIndexer: $categoryIndexer,
            productBatchQueueProcessor: $productBatchQueueProcessor,
        );

        $this->captureAndInvokeCommitCallback($observer, 'afterSave', $category);
    }

    public function testAfterSaveCallbackReindexesProductsWhenNameChanges(): void
    {
        $productIds = [10, 20, 30];
        $store = $this->createStoreStub(1);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([1 => $store]);

        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        $category = $this->createCategoryMock();
        $category->expects($this->once())->method('getId')->willReturn(1);
        $category->method('__call')
            ->willReturnCallback(fn($name, $args) => $name === 'getChangedProductIds' ? [] : null);
        $category->method('getProductCollection')->willReturn($this->createProductCollectionStub($productIds));
        $category->method('getOrigData')->willReturnCallback(
            fn($key) => $key === 'name' ? 'Old Name' : null
        );
        $category->method('getData')->willReturnCallback(
            fn($key) => $key === 'name' ? 'New Name' : null
        );

        $categoryIndexer = $this->createStub(IndexerInterface::class);
        $categoryIndexer->method('isScheduled')->willReturn(false);

        $productBatchQueueProcessor = $this->createMock(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->expects($this->once())
            ->method('processBatch')
            ->with(1, $productIds);

        $observer = $this->createObjectToTest(
            algoliaCredentialsManager: $algoliaCredentialsManager,
            categoryIndexer: $categoryIndexer,
            storeManager: $storeManager,
            productBatchQueueProcessor: $productBatchQueueProcessor,
        );

        $this->captureAndInvokeCommitCallback($observer, 'afterSave', $category);
    }

    public function testAfterSaveCallbackMergesAndDeduplicatesChangedAndCollectionProductIds(): void
    {
        $changedProductIds = [5, 6];
        $collectionIds = [6, 7, 8]; // 6 appears in both
        $store = $this->createStoreStub(1);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([1 => $store]);

        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        $category = $this->createCategoryMock();
        $category->expects($this->once())->method('getId')->willReturn(1);
        $category->method('__call')
            ->willReturnCallback(fn($name, $args) => $name === 'getChangedProductIds' ? $changedProductIds : null);
        $category->method('getProductCollection')->willReturn($this->createProductCollectionStub($collectionIds));
        $category->method('getOrigData')->willReturnCallback(fn($k) => $k === 'name' ? 'Old' : null);
        $category->method('getData')->willReturnCallback(fn($k) => $k === 'name' ? 'New' : null);

        $categoryIndexer = $this->createStub(IndexerInterface::class);
        $categoryIndexer->method('isScheduled')->willReturn(false);

        $productBatchQueueProcessor = $this->createMock(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->expects($this->once())
            ->method('processBatch')
            ->with(1, $this->callback(function (array $ids) {
                sort($ids);
                $this->assertEquals([5, 6, 7, 8], $ids);
                return true;
            }));

        $observer = $this->createObjectToTest(
            algoliaCredentialsManager: $algoliaCredentialsManager,
            categoryIndexer: $categoryIndexer,
            storeManager: $storeManager,
            productBatchQueueProcessor: $productBatchQueueProcessor,
        );

        $this->captureAndInvokeCommitCallback($observer, 'afterSave', $category);
    }

    public function testAfterSaveCallbackUpdatesChangelogWhenScheduledAndHasCollectionIdsButNoChangedProducts(): void
    {
        $collectionIds = [10, 20];
        $changelogTableName = 'algolia_products_cl';

        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        $category = $this->createCategoryMock();
        // Scheduled branch never reaches category->getId().
        $category->expects($this->never())->method('getId');
        $category->method('__call')
            ->willReturnCallback(fn($name, $args) => $name === 'getChangedProductIds' ? [] : null);
        $category->method('getProductCollection')->willReturn($this->createProductCollectionStub($collectionIds));
        // Attribute change to populate collectionIds
        $category->method('getOrigData')->willReturnCallback(fn($k) => $k === 'name' ? 'Old' : null);
        $category->method('getData')->willReturnCallback(fn($k) => $k === 'name' ? 'New' : null);

        $categoryIndexer = $this->createStub(IndexerInterface::class);
        $categoryIndexer->method('isScheduled')->willReturn(true);

        $changelog = $this->createStub(ChangelogInterface::class);
        $changelog->method('getName')->willReturn('algolia_products_cl');
        $view = $this->createStub(ViewInterface::class);
        $view->method('getChangelog')->willReturn($changelog);

        $productIndexer = $this->createStub(IndexerInterface::class);
        $productIndexer->method('isScheduled')->willReturn(true);
        $productIndexer->method('getView')->willReturn($view);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->with($changelogTableName)->willReturn(true);
        $connection->expects($this->once())
            ->method('insertMultiple')
            ->with($changelogTableName, [
                ['entity_id' => 10],
                ['entity_id' => 20],
            ]);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getTableName')->with('algolia_products_cl')->willReturn($changelogTableName);
        $resource->method('getConnection')->willReturn($connection);

        $observer = $this->createObjectToTest(
            algoliaCredentialsManager: $algoliaCredentialsManager,
            categoryIndexer: $categoryIndexer,
            productIndexer: $productIndexer,
            resource: $resource,
        );

        $this->captureAndInvokeCommitCallback($observer, 'afterSave', $category);
    }

    public function testAfterSaveCallbackCallsReindexListWhenScheduledAndProductIndexerNotScheduled(): void
    {
        $collectionIds = [10, 20];

        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        $category = $this->createCategoryMock();
        $category->expects($this->never())->method('getId');
        $category->method('__call')
            ->willReturnCallback(fn($name, $args) => $name === 'getChangedProductIds' ? [] : null);
        $category->method('getProductCollection')->willReturn($this->createProductCollectionStub($collectionIds));
        // Attribute change to populate collectionIds
        $category->method('getOrigData')->willReturnCallback(fn($k) => $k === 'name' ? 'Old' : null);
        $category->method('getData')->willReturnCallback(fn($k) => $k === 'name' ? 'New' : null);

        $categoryIndexer = $this->createStub(IndexerInterface::class);
        $categoryIndexer->method('isScheduled')->willReturn(true);

        $productIndexer = $this->createMock(IndexerInterface::class);
        $productIndexer->method('isScheduled')->willReturn(false);
        $productIndexer->expects($this->once())->method('reindexList')->with($collectionIds);

        $observer = $this->createObjectToTest(
            algoliaCredentialsManager: $algoliaCredentialsManager,
            categoryIndexer: $categoryIndexer,
            productIndexer: $productIndexer,
        );

        $this->captureAndInvokeCommitCallback($observer, 'afterSave', $category);
    }

    public function testAfterSaveCallbackSkipsInsertWhenChangelogTableDoesNotExist(): void
    {
        $collectionIds = [10, 20];

        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        $category = $this->createCategoryMock();
        $category->expects($this->never())->method('getId');
        $category->method('__call')
            ->willReturnCallback(fn($name, $args) => $name === 'getChangedProductIds' ? [] : null);
        $category->method('getProductCollection')->willReturn($this->createProductCollectionStub($collectionIds));
        $category->method('getOrigData')->willReturnCallback(fn($k) => $k === 'name' ? 'Old' : null);
        $category->method('getData')->willReturnCallback(fn($k) => $k === 'name' ? 'New' : null);

        $categoryIndexer = $this->createStub(IndexerInterface::class);
        $categoryIndexer->method('isScheduled')->willReturn(true);

        $changelog = $this->createStub(ChangelogInterface::class);
        $changelog->method('getName')->willReturn('algolia_products_cl');
        $view = $this->createStub(ViewInterface::class);
        $view->method('getChangelog')->willReturn($changelog);

        $productIndexer = $this->createStub(IndexerInterface::class);
        $productIndexer->method('isScheduled')->willReturn(true);
        $productIndexer->method('getView')->willReturn($view);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(false);
        $connection->expects($this->never())->method('insertMultiple');

        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getTableName')->willReturn('algolia_products_cl');
        $resource->method('getConnection')->willReturn($connection);

        $observer = $this->createObjectToTest(
            algoliaCredentialsManager: $algoliaCredentialsManager,
            categoryIndexer: $categoryIndexer,
            productIndexer: $productIndexer,
            resource: $resource,
        );

        $this->captureAndInvokeCommitCallback($observer, 'afterSave', $category);
    }

    public function testAfterSaveCallbackSkipsUpdateCategoryProductsWhenScheduledAndChangedProductsExist(): void
    {
        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        $category = $this->createCategoryMock();
        $category->expects($this->never())->method('getId');
        $category->method('__call')
            ->willReturnCallback(fn($name, $args) => $name === 'getChangedProductIds' ? [10, 20] : null);
        // No attribute changes, so collectionIds stays empty
        $category->method('getOrigData')->willReturn('same');
        $category->method('getData')->willReturn('same');
        $category->method('getProductCollection')->willReturn($this->createProductCollectionStub([]));

        $categoryIndexer = $this->createStub(IndexerInterface::class);
        $categoryIndexer->method('isScheduled')->willReturn(true);

        // updateCategoryProducts should not be triggered (changedProductIds count > 0)
        $productIndexer = $this->createMock(IndexerInterface::class);
        $productIndexer->expects($this->never())->method('reindexList');

        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects($this->never())->method('getConnection');

        $observer = $this->createObjectToTest(
            algoliaCredentialsManager: $algoliaCredentialsManager,
            categoryIndexer: $categoryIndexer,
            productIndexer: $productIndexer,
            resource: $resource,
        );

        $this->captureAndInvokeCommitCallback($observer, 'afterSave', $category);
    }

    // -------------------------------------------------------------------------
    // afterDelete
    // -------------------------------------------------------------------------

    public function testAfterDeleteReturnsResult(): void
    {
        $categoryResource = $this->createStub(CategoryResourceModel::class);
        $categoryResource->method('addCommitCallback');

        $result = $this->createStub(CategoryResourceModel::class);

        $category = $this->createCategoryMock();
        $category->expects($this->never())->method('getId');

        $observer = $this->createObjectToTest();

        $returnValue = $observer->afterDelete($categoryResource, $result, $category);

        $this->assertSame($result, $returnValue);
    }

    public function testAfterDeleteCallbackReindexesCategoryAndProductsWhenNotScheduled(): void
    {
        $categoryId = 15;
        $productPositions = [10 => 0, 20 => 1, 30 => 2];
        $store = $this->createStoreStub(1);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([1 => $store]);

        $category = $this->createCategoryMock();
        $category->expects($this->once())->method('getId')->willReturn($categoryId);
        $category->method('getProductsPosition')->willReturn($productPositions);

        $categoryIndexer = $this->createMock(IndexerInterface::class);
        $categoryIndexer->method('isScheduled')->willReturn(false);
        $categoryIndexer->expects($this->once())->method('reindexRow')->with($categoryId);

        $productBatchQueueProcessor = $this->createMock(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->expects($this->once())
            ->method('processBatch')
            ->with(1, array_keys($productPositions));

        $observer = $this->createObjectToTest(
            categoryIndexer: $categoryIndexer,
            storeManager: $storeManager,
            productBatchQueueProcessor: $productBatchQueueProcessor,
        );

        $this->captureAndInvokeCommitCallback($observer, 'afterDelete', $category);
    }

    public function testAfterDeleteCallbackSkipsReindexWhenScheduled(): void
    {
        // Scheduled → the whole reindex branch is skipped, so category->getId() is never reached.
        $category = $this->createCategoryMock();
        $category->expects($this->never())->method('getId');

        $categoryIndexer = $this->createMock(IndexerInterface::class);
        $categoryIndexer->method('isScheduled')->willReturn(true);
        $categoryIndexer->expects($this->never())->method('reindexRow');

        $productBatchQueueProcessor = $this->createMock(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->expects($this->never())->method('processBatch');

        $observer = $this->createObjectToTest(
            categoryIndexer: $categoryIndexer,
            productBatchQueueProcessor: $productBatchQueueProcessor,
        );

        $this->captureAndInvokeCommitCallback($observer, 'afterDelete', $category);
    }

    // -------------------------------------------------------------------------
    // reindexAffectedProducts (protected — tested via invokeMethod)
    // -------------------------------------------------------------------------

    public function testReindexAffectedProductsSkipsWhenEmpty(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStores');

        $productBatchQueueProcessor = $this->createMock(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->expects($this->never())->method('processBatch');

        $observer = $this->createObjectToTest(
            storeManager: $storeManager,
            productBatchQueueProcessor: $productBatchQueueProcessor,
        );

        $this->invokeMethod($observer, 'reindexAffectedProducts', [[]]);
    }

    public function testReindexAffectedProductsCallsProcessBatchForEachStore(): void
    {
        $productIds = [1, 2, 3];
        $store1 = $this->createStoreStub(1);
        $store2 = $this->createStoreStub(2);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([1 => $store1, 2 => $store2]);

        $productBatchQueueProcessor = $this->createMock(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->expects($this->exactly(2))
            ->method('processBatch')
            ->willReturnCallback(function (int $storeId, array $ids) use ($productIds) {
                $this->assertContains($storeId, [1, 2]);
                $this->assertEquals($productIds, $ids);
            });

        $observer = $this->createObjectToTest(
            storeManager: $storeManager,
            productBatchQueueProcessor: $productBatchQueueProcessor,
        );

        $this->invokeMethod($observer, 'reindexAffectedProducts', [$productIds]);
    }
}
