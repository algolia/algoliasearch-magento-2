<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Model\Cache\Product\IndexCollectionSize;
use Algolia\AlgoliaSearch\Model\IndexMover;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Product\BatchQueueProcessor;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;

class BatchQueueProcessorTest extends TestCase
{
    protected ?Data $dataHelper;
    protected ?ConfigHelper $configHelper;
    protected ?ProductHelper $productHelper;
    protected ?Queue $queue;
    protected ?DiagnosticsLogger $diag;
    protected ?AlgoliaCredentialsManager $algoliaCredentialsManager;
    protected ?IndexBuilder $indexBuilder;
    protected ?IndexCollectionSize $indexCollectionSizeCache;
    protected ?BatchQueueProcessor $processor;

    protected function setUp(): void
    {
        $this->dataHelper = $this->createMock(Data::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->productHelper = $this->createMock(ProductHelper::class);
        $this->queue = $this->createMock(Queue::class);
        $this->diag = $this->createMock(DiagnosticsLogger::class);
        $this->algoliaCredentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $this->indexBuilder = $this->createMock(IndexBuilder::class);
        $this->indexCollectionSizeCache = $this->createMock(IndexCollectionSize::class);

        $this->processor = new BatchQueueProcessor(
            $this->dataHelper,
            $this->configHelper,
            $this->productHelper,
            $this->queue,
            $this->diag,
            $this->algoliaCredentialsManager,
            $this->indexBuilder,
            $this->indexCollectionSizeCache
        );
    }

    /**
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    public function testProcessBatchSkipsWhenIndexingDisabled()
    {
        $this->dataHelper->method('isIndexingEnabled')->willReturn(false);

        $this->algoliaCredentialsManager->expects($this->never())->method('checkCredentialsWithSearchOnlyAPIKey');

        $this->processor->processBatch(1);
    }

    /**
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    public function testProcessBatchSkipsWhenCredentialsInvalid()
    {
        $this->dataHelper->method('isIndexingEnabled')->willReturn(true);
        $this->algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(false);

        $this->algoliaCredentialsManager->expects($this->once())
            ->method('displayErrorMessage')
            ->with(BatchQueueProcessor::class, 1);

        $this->processor->processBatch(1);
    }

    /**
     * @throws NoSuchEntityException
     * @throws DiagnosticsException
     */
    public function testProcessBatchHandlesDeltaIndexing()
    {
        $this->setupBasicIndexingConfig(10);
        $this->productHelper->method('getParentProductIds')->willReturn([]);

        $this->queue->expects($this->once())
            ->method('addToQueue')
            ->with(
                IndexBuilder::class,
                'buildIndexList',
                $this->arrayHasKey('entityIds')
            );

        $this->processor->processBatch(1, range(1,5));
    }

    public function testProcessBatchHandlesDeltaIndexingPaged()
    {
        $pageSize = 10;
        $this->setupBasicIndexingConfig($pageSize);
        $this->productHelper->method('getParentProductIds')->willReturn([]);

        $invocations = $this->exactly(5);
        $this->queue->expects($invocations)
            ->method('addToQueue')
            ->with(
                IndexBuilder::class,
                'buildIndexList',
                $this->callback(function(array $arg) use ($invocations, $pageSize) {
                    return array_key_exists('storeId', $arg)
                        && array_key_exists('entityIds', $arg)
                        && array_key_exists('options', $arg)
                        && $arg['options']['pageSize'] === $pageSize
                        && $arg['options']['page'] === $invocations->getInvocationCount();
                })
            );

        $this->processor->processBatch(1, range(1,50));
    }

    /**
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    public function testProcessBatchHandlesFullIndexing()
    {
        $this->setupBasicIndexingConfig(10);
        $this->configHelper->method('isQueueActive')->willReturn(false);
        $this->indexCollectionSizeCache->expects($this->once())->method('get')->willReturn(10);
        $this->productHelper->method('getProductCollectionQuery')->willReturn($this->getMockCollection());

        $invocations = $this->exactly(2);
        $this->queue->expects($invocations)
            ->method('addToQueue')
            ->willReturnCallback(
                function(
                    string $className,
                    string $method,
                    array $data,
                    int $dataSize,
                    bool $isFullReindex)
                use ($invocations) {
                    switch ($invocations->getInvocationCount()) {
                        case 1:
                            $this->assertEquals(IndicesConfigurator::class, $className);
                            $this->assertEquals('saveConfigurationToAlgolia', $method);
                            $this->assertArrayHasKey('storeId', $data);
                            break;
                        case 2:
                            $this->assertEquals(IndexBuilder::class, $className);
                            $this->assertEquals('buildIndexFull', $method);
                            $this->assertArrayHasKey('storeId', $data);
                            break;
                    }
                }
            );

        $this->processor->processBatch(1);
    }

    /**
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    public function testProcessBatchFullIndexingWithNoCache()
    {
        $this->setupBasicIndexingConfig(10);
        $this->configHelper->method('isQueueActive')->willReturn(false);
        $this->indexCollectionSizeCache->expects($this->once())->method('get')->willReturn(IndexCollectionSize::NOT_FOUND);
        $this->productHelper->method('getProductCollectionQuery')->willReturn($this->getMockCollection(10, 1));

        $this->queue->expects($this->exactly(2))
            ->method('addToQueue');

        $this->processor->processBatch(1);
    }

    /**
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    public function testProcessBatchHandlesFullIndexingPaged()
    {
        $pageSize = 10;
        $this->setupBasicIndexingConfig($pageSize);
        $this->configHelper->method('isQueueActive')->willReturn(false);
        $this->indexCollectionSizeCache->expects($this->once())->method('get')->willReturn(50);
        $this->productHelper->method('getProductCollectionQuery')->willReturn($this->getMockCollection());

        $invocations = $this->exactly(6);
        $this->queue->expects($invocations)
            ->method('addToQueue')
            ->willReturnCallback(
                function(
                    string $className,
                    string $method,
                    array $data,
                    int $dataSize,
                    bool $isFullReindex)
                use ($invocations, $pageSize) {
                    $invocation = $invocations->getInvocationCount();
                    switch ($invocation) {
                        case 1:
                            $this->assertEquals(IndicesConfigurator::class, $className);
                            $this->assertEquals('saveConfigurationToAlgolia', $method);
                            break;
                        default:
                            $this->assertEquals(IndexBuilder::class, $className);
                            $this->assertEquals('buildIndexFull', $method);
                            $this->assertArrayHasKey('options', $data);
                            $this->assertEquals($pageSize, $data['options']['pageSize']);
                            $this->assertEquals($invocation - 1, $data['options']['page']);
                            break;
                    }
                }
            );

        $this->processor->processBatch(1);
    }

    /**
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    public function testProcessBatchMovesTempIndexIfQueueActive()
    {
        $this->setupBasicIndexingConfig(10);
        $this->configHelper->method('isQueueActive')->willReturn(true);
        $this->indexCollectionSizeCache->expects($this->once())->method('get')->willReturn(10);

        $this->productHelper->method('getProductCollectionQuery')->willReturn($this->getMockCollection());
        $this->productHelper->method('getTempIndexName')->willReturn('tmp_index');
        $this->productHelper->method('getIndexName')->willReturn('main_index');

        $invocations = $this->exactly(3);
        $this->queue->expects($invocations)
            ->method('addToQueue')
            ->willReturnCallback(
                function(
                    string $className,
                    string $method,
                    array $data,
                    int $dataSize,
                    bool $isFullReindex)
                use ($invocations) {
                    if ($invocations->getInvocationCount() === 3) {
                        $this->assertEquals(IndexMover::class, $className);
                        $this->assertEquals('moveIndexWithSetSettings', $method);
                        $this->assertArrayHasKey('tmpIndexName', $data);
                        $this->assertArrayHasKey('indexName', $data);
                        $this->assertArrayHasKey('storeId', $data);
                    }
                }
            );

        $this->processor->processBatch(1);
    }

    protected function setupBasicIndexingConfig(int $elementsPerPage): void
    {
        $this->dataHelper->method('isIndexingEnabled')->willReturn(true);
        $this->algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);
        $this->configHelper->method('getNumberOfElementByPage')->willReturn($elementsPerPage);
        $this->configHelper->method('includeNonVisibleProductsInIndex')->willReturn(false);
    }

    protected function getMockCollection(int $size = 10, int $expectedSizeCalls = 0): Collection
    {
        $mockCollection = $this->createMock(Collection::class);
        $mockCollection->expects($this->exactly($expectedSizeCalls))->method('getSize')->willReturn($size);
        return $mockCollection;
    }
}
