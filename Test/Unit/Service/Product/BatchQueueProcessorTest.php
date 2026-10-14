<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Model\Cache\Product\IndexCollectionSize;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobContext;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobContextFactory;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobDefinition;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobDefinitionFactory;
use Algolia\AlgoliaSearch\Model\IndexMover;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Index\Settings\IndexSettingsComparator;
use Algolia\AlgoliaSearch\Service\Product\BatchQueueProcessor;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;

class BatchQueueProcessorTest extends TestCase
{
    protected function createObjectToTest(
        ?Data $dataHelper = null,
        ?ConfigHelper $configHelper = null,
        ?ProductHelper $productHelper = null,
        ?QueueHelper $queueHelper = null,
        ?Queue $queue = null,
        ?AlgoliaCredentialsManager $algoliaCredentialsManager = null,
        ?IndexCollectionSize $indexCollectionSizeCache = null,
        ?JobDefinitionFactory $jobDefinitionFactory = null,
        ?JobContextFactory $jobContextFactory = null,
    ): BatchQueueProcessor {
        return new BatchQueueProcessor(
            $dataHelper ?? $this->createStub(Data::class),
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $productHelper ?? $this->createStub(ProductHelper::class),
            $queueHelper ?? $this->createStub(QueueHelper::class),
            $queue ?? $this->createStub(Queue::class),
            $this->createStub(DiagnosticsLogger::class),
            $algoliaCredentialsManager ?? $this->createStub(AlgoliaCredentialsManager::class),
            $this->createStub(IndexBuilder::class),
            $indexCollectionSizeCache ?? $this->createStub(IndexCollectionSize::class),
            $this->createStub(IndexOptionsBuilder::class),
            $this->createStub(IndexSettingsComparator::class),
            $jobDefinitionFactory ?? $this->createJobDefinitionFactory(),
            $jobContextFactory ?? $this->createJobContextFactory(),
        );
    }

    private function createJobDefinitionFactory(): JobDefinitionFactory
    {
        $factory = $this->createStub(JobDefinitionFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn (array $data = []) => new JobDefinition(
                $data['className'],
                $data['method'],
                $data['storeId'],
                $data['data'] ?? [],
            )
        );

        return $factory;
    }

    private function createJobContextFactory(): JobContextFactory
    {
        $factory = $this->createStub(JobContextFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn (array $data = []) => new JobContext(
                $data['dataSize'] ?? 1,
                $data['isFullReindex'] ?? false,
            )
        );

        return $factory;
    }

    /**
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    public function testProcessBatchSkipsWhenIndexingDisabled()
    {
        $dataHelper = $this->createStub(Data::class);
        $dataHelper->method('isIndexingEnabled')->willReturn(false);

        $algoliaCredentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->expects($this->never())->method('checkCredentialsWithSearchOnlyAPIKey');

        $processor = $this->createObjectToTest($dataHelper, algoliaCredentialsManager: $algoliaCredentialsManager);

        $processor->processBatch(1);
    }

    /**
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    public function testProcessBatchSkipsWhenCredentialsInvalid()
    {
        $dataHelper = $this->createStub(Data::class);
        $dataHelper->method('isIndexingEnabled')->willReturn(true);

        $algoliaCredentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(false);
        $algoliaCredentialsManager->expects($this->once())
            ->method('displayErrorMessage')
            ->with(BatchQueueProcessor::class, 1);

        $processor = $this->createObjectToTest($dataHelper, algoliaCredentialsManager: $algoliaCredentialsManager);

        $processor->processBatch(1);
    }

    /**
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    public function testProcessBatchHandlesDeltaIndexing()
    {
        [$dataHelper, $algoliaCredentialsManager, $configHelper] = $this->createBasicIndexingConfig(10);

        $productHelper = $this->createStub(ProductHelper::class);
        $productHelper->method('getParentProductIds')->willReturn([]);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())
            ->method('addJob')
            ->with($this->callback(function (JobDefinition $job) {
                return $job->className === IndexBuilder::class
                    && $job->method === 'buildIndexList'
                    && $job->storeId === 1
                    && array_key_exists('entityIds', $job->data)
                    && !array_key_exists('storeId', $job->data);
            }));

        $processor = $this->createObjectToTest($dataHelper, $configHelper, $productHelper, queue: $queue, algoliaCredentialsManager: $algoliaCredentialsManager);

        $processor->processBatch(1, range(1, 5));
    }

    public function testProcessBatchHandlesDeltaIndexingPaged()
    {
        $pageSize = 10;
        [$dataHelper, $algoliaCredentialsManager, $configHelper] = $this->createBasicIndexingConfig($pageSize);

        $productHelper = $this->createStub(ProductHelper::class);
        $productHelper->method('getParentProductIds')->willReturn([]);

        $invocationCount = 0;
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->exactly(5))
            ->method('addJob')
            ->with($this->callback(function (JobDefinition $job) use (&$invocationCount, $pageSize) {
                $invocationCount++;

                return $job->className === IndexBuilder::class
                    && $job->method === 'buildIndexList'
                    && $job->storeId === 1
                    && !array_key_exists('storeId', $job->data)
                    && array_key_exists('entityIds', $job->data)
                    && array_key_exists('options', $job->data)
                    && $job->data['options']['pageSize'] === $pageSize
                    && $job->data['options']['page'] === $invocationCount;
            }));

        $processor = $this->createObjectToTest($dataHelper, $configHelper, $productHelper, queue: $queue, algoliaCredentialsManager: $algoliaCredentialsManager);

        $processor->processBatch(1, range(1, 50));
    }

    /**
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    public function testProcessBatchHandlesFullIndexing()
    {
        [$dataHelper, $algoliaCredentialsManager, $configHelper] = $this->createBasicIndexingConfig(10);
        $configHelper->method('isQueueActive')->willReturn(false);

        $indexCollectionSizeCache = $this->createMock(IndexCollectionSize::class);
        $indexCollectionSizeCache->expects($this->once())->method('get')->willReturn(10);

        $productHelper = $this->createStub(ProductHelper::class);
        $productHelper->method('getProductCollectionQuery')->willReturn($this->getStubCollection());

        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('useTmpIndex')->willReturn(false);

        $invocationCount = 0;
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->exactly(2))
            ->method('addJob')
            ->willReturnCallback(
                function (JobDefinition $job, JobContext $context) use (&$invocationCount) {
                    $invocationCount++;
                    switch ($invocationCount) {
                        case 1:
                            $this->assertEquals(IndicesConfigurator::class, $job->className);
                            $this->assertEquals('saveConfigurationToAlgolia', $job->method);
                            $this->assertEquals(1, $job->storeId);
                            $this->assertArrayNotHasKey('storeId', $job->data);
                            $this->assertTrue($context->isFullReindex);
                            $this->assertEquals(1, $context->dataSize);

                            break;
                        case 2:
                            $this->assertEquals(IndexBuilder::class, $job->className);
                            $this->assertEquals('buildIndexFull', $job->method);
                            $this->assertEquals(1, $job->storeId);
                            $this->assertArrayNotHasKey('storeId', $job->data);
                            $this->assertTrue($context->isFullReindex);
                            $this->assertEquals(10, $context->dataSize);

                            break;
                    }
                }
            );

        $processor = $this->createObjectToTest(
            $dataHelper,
            $configHelper,
            $productHelper,
            $queueHelper,
            $queue,
            $algoliaCredentialsManager,
            $indexCollectionSizeCache,
        );

        $processor->processBatch(1);
    }

    /**
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    public function testProcessBatchFullIndexingWithNoCache()
    {
        [$dataHelper, $algoliaCredentialsManager, $configHelper] = $this->createBasicIndexingConfig(10);
        $configHelper->method('isQueueActive')->willReturn(false);

        $indexCollectionSizeCache = $this->createMock(IndexCollectionSize::class);
        $indexCollectionSizeCache->expects($this->once())->method('get')->willReturn(IndexCollectionSize::NOT_FOUND);

        $productHelper = $this->createStub(ProductHelper::class);
        $productHelper->method('getProductCollectionQuery')->willReturn($this->getStubCollection(10, 1));

        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('useTmpIndex')->willReturn(false);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->exactly(2))->method('addJob');

        $processor = $this->createObjectToTest(
            $dataHelper,
            $configHelper,
            $productHelper,
            $queueHelper,
            $queue,
            $algoliaCredentialsManager,
            $indexCollectionSizeCache,
        );

        $processor->processBatch(1);
    }

    /**
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    public function testProcessBatchHandlesFullIndexingPaged()
    {
        $pageSize = 10;
        [$dataHelper, $algoliaCredentialsManager, $configHelper] = $this->createBasicIndexingConfig($pageSize);
        $configHelper->method('isQueueActive')->willReturn(false);

        $indexCollectionSizeCache = $this->createMock(IndexCollectionSize::class);
        $indexCollectionSizeCache->expects($this->once())->method('get')->willReturn(50);

        $productHelper = $this->createStub(ProductHelper::class);
        $productHelper->method('getProductCollectionQuery')->willReturn($this->getStubCollection());

        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('useTmpIndex')->willReturn(false);

        $invocationCount = 0;
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->exactly(6))
            ->method('addJob')
            ->willReturnCallback(
                function (JobDefinition $job, JobContext $context) use (&$invocationCount, $pageSize) {
                    $invocationCount++;
                    switch ($invocationCount) {
                        case 1:
                            $this->assertEquals(IndicesConfigurator::class, $job->className);
                            $this->assertEquals('saveConfigurationToAlgolia', $job->method);
                            $this->assertEquals(1, $job->storeId);

                            break;
                        default:
                            $this->assertEquals(IndexBuilder::class, $job->className);
                            $this->assertEquals('buildIndexFull', $job->method);
                            $this->assertEquals(1, $job->storeId);
                            $this->assertArrayNotHasKey('storeId', $job->data);
                            $this->assertArrayHasKey('options', $job->data);
                            $this->assertEquals($pageSize, $job->data['options']['pageSize']);
                            $this->assertEquals($invocationCount - 1, $job->data['options']['page']);
                            $this->assertTrue($context->isFullReindex);
                            $this->assertEquals($pageSize, $context->dataSize);

                            break;
                    }
                }
            );

        $processor = $this->createObjectToTest(
            $dataHelper,
            $configHelper,
            $productHelper,
            $queueHelper,
            $queue,
            $algoliaCredentialsManager,
            $indexCollectionSizeCache,
        );

        $processor->processBatch(1);
    }

    /**
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    public function testProcessBatchMovesTempIndexIfQueueActive()
    {
        [$dataHelper, $algoliaCredentialsManager, $configHelper] = $this->createBasicIndexingConfig(10);
        $configHelper->method('isQueueActive')->willReturn(true);

        $indexCollectionSizeCache = $this->createMock(IndexCollectionSize::class);
        $indexCollectionSizeCache->expects($this->once())->method('get')->willReturn(10);

        $productHelper = $this->createStub(ProductHelper::class);
        $productHelper->method('getProductCollectionQuery')->willReturn($this->getStubCollection());
        $productHelper->method('getTempIndexName')->willReturn('tmp_index');
        $productHelper->method('getIndexName')->willReturn('main_index');

        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('useTmpIndex')->willReturn(true);

        $invocationCount = 0;
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->exactly(3))
            ->method('addJob')
            ->willReturnCallback(
                function (JobDefinition $job, JobContext $context) use (&$invocationCount) {
                    $invocationCount++;
                    if ($invocationCount === 3) {
                        $this->assertEquals(IndexMover::class, $job->className);
                        $this->assertEquals('moveIndexWithSetSettings', $job->method);
                        $this->assertEquals(1, $job->storeId);
                        $this->assertArrayHasKey('tmpIndexName', $job->data);
                        $this->assertArrayHasKey('indexName', $job->data);
                        $this->assertArrayNotHasKey('storeId', $job->data);
                        $this->assertTrue($context->isFullReindex);
                    }
                }
            );

        $processor = $this->createObjectToTest(
            $dataHelper,
            $configHelper,
            $productHelper,
            $queueHelper,
            $queue,
            $algoliaCredentialsManager,
            $indexCollectionSizeCache,
        );

        $processor->processBatch(1);
    }

    /**
     * @return array{0: Data, 1: AlgoliaCredentialsManager, 2: ConfigHelper}
     */
    protected function createBasicIndexingConfig(int $elementsPerPage): array
    {
        $dataHelper = $this->createStub(Data::class);
        $dataHelper->method('isIndexingEnabled')->willReturn(true);

        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('getNumberOfElementByPage')->willReturn($elementsPerPage);
        $configHelper->method('includeNonVisibleProductsInIndex')->willReturn(false);

        return [$dataHelper, $algoliaCredentialsManager, $configHelper];
    }

    protected function getStubCollection(int $size = 10, int $expectedSizeCalls = 0): Collection
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->exactly($expectedSizeCalls))->method('getSize')->willReturn($size);

        return $collection;
    }
}
