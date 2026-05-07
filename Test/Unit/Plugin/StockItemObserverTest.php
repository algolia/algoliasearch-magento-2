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
use PHPUnit\Framework\MockObject\MockObject;

class StockItemObserverTest extends TestCase
{
    protected null|(Indexer&MockObject) $indexer = null;
    protected null|(IndexerRegistry&MockObject) $indexerRegistry = null;
    protected null|(StockItemResource&MockObject) $stockItemResource = null;
    protected ?StockItemObserver $plugin = null;

    protected function setUp(): void
    {
        $this->indexer = $this->createMock(Indexer::class);
        $this->indexerRegistry = $this->createMock(IndexerRegistry::class);
        $this->indexerRegistry->method('get')->with('algolia_products')->willReturn($this->indexer);
        $this->stockItemResource = $this->createMock(StockItemResource::class);

        $this->plugin = new StockItemObserver($this->indexerRegistry);
    }

    public function testBeforeSaveReindexesProductWhenIndexerNotScheduled(): void
    {
        $stockItem = $this->getMockBuilder(AbstractModel::class)
            ->disableOriginalConstructor()
            ->addMethods(['getProductId'])
            ->getMock();
        $stockItem->method('getProductId')->willReturn(42);

        $this->stockItemResource->expects($this->once())
            ->method('addCommitCallback')
            ->with($this->isType('callable'))
            ->willReturnCallback(fn(callable $cb) => $cb());

        $this->indexer->method('isScheduled')->willReturn(false);
        $this->indexer->expects($this->once())->method('reindexRow')->with(42);

        $this->plugin->beforeSave($this->stockItemResource, $stockItem);
    }

    public function testBeforeSaveSkipsReindexWhenIndexerIsScheduled(): void
    {
        $stockItem = $this->getMockBuilder(AbstractModel::class)
            ->disableOriginalConstructor()
            ->addMethods(['getProductId'])
            ->getMock();

        $this->stockItemResource->expects($this->once())
            ->method('addCommitCallback')
            ->willReturnCallback(fn(callable $cb) => $cb());

        $this->indexer->method('isScheduled')->willReturn(true);
        $this->indexer->expects($this->never())->method('reindexRow');

        $this->plugin->beforeSave($this->stockItemResource, $stockItem);
    }

    public function testAfterDeleteReindexesProductWhenIndexerNotScheduled(): void
    {
        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getProductId')->willReturn(99);

        $result = $this->createMock(StockItemResource::class);

        $this->stockItemResource->expects($this->once())
            ->method('addCommitCallback')
            ->with($this->isType('callable'))
            ->willReturnCallback(fn(callable $cb) => $cb());

        $this->indexer->method('isScheduled')->willReturn(false);
        $this->indexer->expects($this->once())->method('reindexRow')->with(99);

        $returnedResult = $this->plugin->afterDelete($this->stockItemResource, $result, $stockItem);

        $this->assertSame($result, $returnedResult);
    }

    public function testAfterDeleteSkipsReindexWhenIndexerIsScheduled(): void
    {
        $stockItem = $this->createMock(StockItemInterface::class);
        $result = $this->createMock(StockItemResource::class);

        $this->stockItemResource->expects($this->once())
            ->method('addCommitCallback')
            ->willReturnCallback(fn(callable $cb) => $cb());

        $this->indexer->method('isScheduled')->willReturn(true);
        $this->indexer->expects($this->never())->method('reindexRow');

        $this->plugin->afterDelete($this->stockItemResource, $result, $stockItem);
    }
}
