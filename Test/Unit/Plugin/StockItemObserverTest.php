<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin;

use Algolia\AlgoliaSearch\Plugin\StockItemObserver;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Item as StockItemResource;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel;
use Magento\Indexer\Model\Indexer;

class StockItemObserverTest extends TestCase
{
    protected function createObjectToTest(?Indexer $indexer = null): StockItemObserver
    {
        $indexerRegistry = $this->createMock(IndexerRegistry::class);
        $indexerRegistry->method('get')->with('algolia_products')->willReturn($indexer ?? $this->createStub(Indexer::class));

        return new StockItemObserver($indexerRegistry);
    }

    public function testBeforeSaveReindexesProductWhenIndexerNotScheduled(): void
    {
        // getProductId() is a magic getter (via DataObject::__call), so __call is stubbed
        // to control it rather than mocking an undeclared method. It's a feeder of canned
        // data (indirect input), not behavior under test, so it stays a plain stub.
        $stockItem = $this->createStub(AbstractModel::class);
        $stockItem->method('__call')->willReturnCallback(fn($name, $args) => $name === 'getProductId' ? 42 : null);

        $indexer = $this->createMock(Indexer::class);
        $indexer->method('isScheduled')->willReturn(false);
        $indexer->expects($this->once())->method('reindexRow')->with(42);

        $stockItemResource = $this->createMock(StockItemResource::class);
        $stockItemResource->expects($this->once())
            ->method('addCommitCallback')
            ->with($this->callback('is_callable'))
            ->willReturnCallback(fn(callable $cb) => $cb());

        $plugin = $this->createObjectToTest($indexer);

        $plugin->beforeSave($stockItemResource, $stockItem);
    }

    public function testBeforeSaveSkipsReindexWhenIndexerIsScheduled(): void
    {
        // Indexer is scheduled → getProductId() is never reached, so __call needs no configuration.
        $stockItem = $this->createStub(AbstractModel::class);

        $indexer = $this->createMock(Indexer::class);
        $indexer->method('isScheduled')->willReturn(true);
        $indexer->expects($this->never())->method('reindexRow');

        $stockItemResource = $this->createMock(StockItemResource::class);
        $stockItemResource->expects($this->once())
            ->method('addCommitCallback')
            ->willReturnCallback(fn(callable $cb) => $cb());

        $plugin = $this->createObjectToTest($indexer);

        $plugin->beforeSave($stockItemResource, $stockItem);
    }

    public function testAfterDeleteReindexesProductWhenIndexerNotScheduled(): void
    {
        $stockItem = $this->createStub(StockItemInterface::class);
        $stockItem->method('getProductId')->willReturn(99);

        $result = $this->createStub(StockItemResource::class);

        $indexer = $this->createMock(Indexer::class);
        $indexer->method('isScheduled')->willReturn(false);
        $indexer->expects($this->once())->method('reindexRow')->with(99);

        $stockItemResource = $this->createMock(StockItemResource::class);
        $stockItemResource->expects($this->once())
            ->method('addCommitCallback')
            ->with($this->callback('is_callable'))
            ->willReturnCallback(fn(callable $cb) => $cb());

        $plugin = $this->createObjectToTest($indexer);

        $returnedResult = $plugin->afterDelete($stockItemResource, $result, $stockItem);

        $this->assertSame($result, $returnedResult);
    }

    public function testAfterDeleteSkipsReindexWhenIndexerIsScheduled(): void
    {
        $stockItem = $this->createStub(StockItemInterface::class);
        $result = $this->createStub(StockItemResource::class);

        $indexer = $this->createMock(Indexer::class);
        $indexer->method('isScheduled')->willReturn(true);
        $indexer->expects($this->never())->method('reindexRow');

        $stockItemResource = $this->createMock(StockItemResource::class);
        $stockItemResource->expects($this->once())
            ->method('addCommitCallback')
            ->willReturnCallback(fn(callable $cb) => $cb());

        $plugin = $this->createObjectToTest($indexer);

        $plugin->afterDelete($stockItemResource, $result, $stockItem);
    }
}
