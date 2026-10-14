<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobContext;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobContextFactory;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobDefinition;
use Algolia\AlgoliaSearch\Model\IndexMover;
use Algolia\AlgoliaSearch\Model\Job;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResourceModel;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job\Collection;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job\CollectionFactory as JobCollectionFactory;
use Algolia\AlgoliaSearch\Service\Category\IndexBuilder as CategoryIndexBuilder;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder as ProductIndexBuilder;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\Console\Output\ConsoleOutput;
use Zend_Db_Statement_Interface;

class QueueTest extends TestCase
{
    /**
     * The unit test bootstrap converts E_USER_DEPRECATED into an exception, so calls into the
     * #[\Deprecated] addToQueue() shell are masked (PHP 8.4+) to keep asserting its behavior.
     */
    private function callDeprecated(callable $call): void
    {
        $previousLevel = error_reporting(error_reporting() & ~E_USER_DEPRECATED);

        try {
            $call();
        } finally {
            error_reporting($previousLevel);
        }
    }

    /**
     * Queue::__construct() resolves its db connection via $objectManager->create(...), an
     * injected instance call (not a static ObjectManager access), so it's safe to construct for
     * real once that instance is stubbed to hand back the desired $dbAdapter.
     */
    protected function createObjectToTest(
        ?ConfigHelper $configHelper = null,
        ?DiagnosticsLogger $logger = null,
        ?ObjectManagerInterface $objectManager = null,
        ?AdapterInterface $dbAdapter = null,
        ?JobCollectionFactory $jobCollectionFactory = null,
        ?JobContextFactory $jobContextFactory = null,
    ): Queue {
        $dbAdapter ??= $this->createStub(AdapterInterface::class);

        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getTableName')->willReturnArgument(0);
        $resourceConnection->method('getConnection')->willReturn($dbAdapter);

        $objectManager ??= $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('create')->willReturn($resourceConnection);

        $jobContextFactory ??= $this->createStub(JobContextFactory::class);
        $jobContextFactory->method('create')->willReturn(new JobContext());

        return new Queue(
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $logger ?? $this->createStub(DiagnosticsLogger::class),
            $jobCollectionFactory ?? $this->createStub(JobCollectionFactory::class),
            $resourceConnection,
            $objectManager,
            $this->createStub(ConsoleOutput::class),
            $jobContextFactory,
        );
    }

    #[DataProvider('authorizedHandlersProvider')]
    public function testAddToQueueSucceedsForAuthorizedHandlerWhenQueueInactive(
        string $class,
        string $method,
        array $data
    ): void {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(false);

        $mockHandler = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods([$method])
            ->getMock();

        $mockHandler->expects($this->once())->method($method);

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->once())
            ->method('get')
            ->with($class)
            ->willReturn($mockHandler);

        $queue = $this->createObjectToTest(configHelper: $configHelper, objectManager: $objectManager);

        $this->callDeprecated(fn () => $queue->addToQueue($class, $method, $data));
    }

    #[DataProvider('authorizedHandlersProvider')]
    public function testAddToQueueSucceedsForAuthorizedHandlerWhenQueueActive(
        string $class,
        string $method,
        array $data
    ): void {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(true);

        $dbAdapter = $this->createMock(AdapterInterface::class);
        $dbAdapter->expects($this->once())->method('insert');

        $queue = $this->createObjectToTest(configHelper: $configHelper, dbAdapter: $dbAdapter);

        $this->callDeprecated(fn () => $queue->addToQueue($class, $method, $data));
    }

    #[DataProvider('unauthorizedHandlersProvider')]
    public function testAddToQueueThrowsForUnauthorizedHandlersWhenQueueInactive(string $class, string $method): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(false);

        $queue = $this->createObjectToTest(configHelper: $configHelper);

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Unauthorized job handler');

        $this->callDeprecated(fn () => $queue->addToQueue($class, $method, []));
    }

    #[DataProvider('unauthorizedHandlersProvider')]
    public function testAddToQueueThrowsForUnauthorizedHandlersWhenQueueActive(string $class, string $method): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(true);

        $dbAdapter = $this->createMock(AdapterInterface::class);
        $dbAdapter->expects($this->never())->method('insert');

        $queue = $this->createObjectToTest(configHelper: $configHelper, dbAdapter: $dbAdapter);

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Unauthorized job handler');

        $this->callDeprecated(fn () => $queue->addToQueue($class, $method, []));
    }

    /**
     * Verify that Queue uses the same whitelist as Job
     */
    public function testAddToQueueReferencesJobAllowedHandlers(): void
    {
        $this->assertNotEmpty(Job::ALLOWED_HANDLERS);
        $this->assertArrayHasKey(ProductIndexBuilder::class, Job::ALLOWED_HANDLERS);
        $this->assertContains('buildIndex', Job::ALLOWED_HANDLERS[ProductIndexBuilder::class]);
    }

    public function testAddToQueueExtractsStoreIdColumnFromPayloadAndStripsTheKey(): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(true);

        $insertedRows = [];
        $dbAdapter = $this->createMock(AdapterInterface::class);
        $dbAdapter->expects($this->exactly(2))
            ->method('insert')
            ->willReturnCallback(function (string $table, array $bind) use (&$insertedRows) {
                $insertedRows[] = $bind;

                return 1;
            });

        $logger = $this->createMock(DiagnosticsLogger::class);
        $logger->expects($this->exactly(2))
            ->method('log')
            ->with($this->stringContains('Queue::addToQueue() is deprecated'));

        $queue = $this->createObjectToTest(configHelper: $configHelper, logger: $logger, dbAdapter: $dbAdapter);

        $this->callDeprecated(fn () => $queue->addToQueue(
            ProductIndexBuilder::class,
            'buildIndex',
            ['storeId' => '1', 'entityIds' => [1, 2, 3], 'options' => []]
        ));
        $this->callDeprecated(fn () => $queue->addToQueue(
            ProductIndexBuilder::class,
            'buildIndex',
            ['entityIds' => [4, 5, 6], 'options' => []]
        ));

        // In-payload store is extracted into the column; the key never reaches the payload
        $this->assertSame(1, $insertedRows[0]['store_id']);
        $payload = json_decode($insertedRows[0]['data'], true);
        $this->assertArrayNotHasKey('storeId', $payload);
        $this->assertSame(['entityIds' => [1, 2, 3], 'options' => []], $payload);

        $this->assertNull($insertedRows[1]['store_id']);
    }

    public function testAddJobWritesStoreIdColumnFromJobDefinition(): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(true);

        $insertedRows = [];
        $dbAdapter = $this->createMock(AdapterInterface::class);
        $dbAdapter->expects($this->once())
            ->method('insert')
            ->willReturnCallback(function (string $table, array $bind) use (&$insertedRows) {
                $insertedRows[] = $bind;

                return 1;
            });

        $queue = $this->createObjectToTest(configHelper: $configHelper, dbAdapter: $dbAdapter);

        $queue->addJob(new JobDefinition(
            ProductIndexBuilder::class,
            'buildIndex',
            3,
            ['entityIds' => [1, 2, 3], 'options' => []]
        ));

        $this->assertSame(3, $insertedRows[0]['store_id']);

        $payload = json_decode($insertedRows[0]['data'], true);
        $this->assertArrayNotHasKey('storeId', $payload);
    }

    public function testAddJobBoltsStoreOnByHandlerNameWhenQueueInactive(): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(false);

        $receivedArgs = [];
        $mockHandler = $this->getMockBuilder(ProductIndexBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildIndex'])
            ->getMock();
        $mockHandler->expects($this->once())
            ->method('buildIndex')
            ->willReturnCallback(function (...$args) use (&$receivedArgs) {
                $receivedArgs = $args;
            });

        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn($mockHandler);

        $queue = $this->createObjectToTest(configHelper: $configHelper, objectManager: $objectManager);

        $queue->addJob(new JobDefinition(
            ProductIndexBuilder::class,
            'buildIndex',
            3,
            ['entityIds' => [1, 2, 3], 'options' => []]
        ));

        // Named arguments land positionally in the mock's declared signature:
        // buildIndex(int $storeId, ?array $entityIds, ?array $options)
        $this->assertSame(3, $receivedArgs[0]);
        $this->assertSame([1, 2, 3], $receivedArgs[1]);
        $this->assertSame([], $receivedArgs[2]);
    }

    /**
     * Builds a JobCollectionFactory stub that yields one stubbed Collection per create() call,
     * appending every addFieldToFilter() call to $recordedFilters (one entry per collection).
     * Collections return no items, so a single run() claims through the full-reindex and
     * realtime fetches only.
     *
     * @param array[] $recordedFilters
     */
    private function createRecordingCollectionFactory(array &$recordedFilters): JobCollectionFactory
    {
        $collectionFactory = $this->createStub(JobCollectionFactory::class);
        $collectionFactory->method('create')->willReturnCallback(function () use (&$recordedFilters) {
            $collectionFilters = [];
            $recordedFilters[] = &$collectionFilters;

            $select = $this->createStub(Select::class);
            $select->method('limit')->willReturnSelf();
            $select->method('forUpdate')->willReturnSelf();

            $collection = $this->createStub(Collection::class);
            $collection->method('addFieldToFilter')->willReturnCallback(
                function ($field, $condition) use (&$collectionFilters, $collection) {
                    $collectionFilters[] = [$field, $condition];

                    return $collection;
                }
            );
            $collection->method('setOrder')->willReturnSelf();
            $collection->method('getSelect')->willReturn($select);
            $collection->method('getItems')->willReturn([]);

            return $collection;
        });

        return $collectionFactory;
    }

    private function createQueueAdapter(): AdapterInterface&Stub
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $dbAdapter = $this->createStub(AdapterInterface::class);
        $dbAdapter->method('select')->willReturn($select);

        return $dbAdapter;
    }

    public function testRunWithStoreIdFiltersAllClaimQueriesByStore(): void
    {
        $recordedFilters = [];
        $dbAdapter = $this->createQueueAdapter();

        $queue = $this->createObjectToTest(
            dbAdapter: $dbAdapter,
            jobCollectionFactory: $this->createRecordingCollectionFactory($recordedFilters),
        );

        $queue->run(10, 3);

        // Two queries are recorded: one for the full-reindex and one for the realtime fetch.
        $this->assertCount(2, $recordedFilters);

        foreach ($recordedFilters as $collectionFilters) {
            $this->assertContains(['store_id', 3], $collectionFilters);
        }
    }

    public function testRunWithNoStoreIdAppliesNoStoreFilter(): void
    {
        $recordedFilters = [];
        $dbAdapter = $this->createQueueAdapter();

        $queue = $this->createObjectToTest(
            dbAdapter: $dbAdapter,
            jobCollectionFactory: $this->createRecordingCollectionFactory($recordedFilters),
        );

        $queue->run(10);

        $this->assertCount(2, $recordedFilters);

        foreach ($recordedFilters as $collectionFilters) {
            $this->assertNotContains('store_id', array_column($collectionFilters, 0));
        }
    }

    public function testClearOldFailingJobsIsScopedToStore(): void
    {
        $deletedCriteria = [];
        $recordedFilters = [];
        $dbAdapter = $this->createQueueAdapter();
        $dbAdapter->method('delete')->willReturnCallback(
            function (string $table, $where) use (&$deletedCriteria) {
                $deletedCriteria[] = $where;

                return 1;
            }
        );

        $queue = $this->createObjectToTest(
            dbAdapter: $dbAdapter,
            jobCollectionFactory: $this->createRecordingCollectionFactory($recordedFilters),
        );

        $queue->run(10, 7);

        $this->assertContains('retries >= max_retries AND store_id = 7', $deletedCriteria);
    }

    public function testClearOldFailingJobsIsUnscopedWithoutStore(): void
    {
        $deletedCriteria = [];
        $recordedFilters = [];
        $dbAdapter = $this->createQueueAdapter();
        $dbAdapter->method('delete')->willReturnCallback(
            function (string $table, $where) use (&$deletedCriteria) {
                $deletedCriteria[] = $where;

                return 1;
            }
        );

        $queue = $this->createObjectToTest(
            dbAdapter: $dbAdapter,
            jobCollectionFactory: $this->createRecordingCollectionFactory($recordedFilters),
        );

        $queue->run(10);

        $this->assertContains(Queue::FAILED_JOB_ARCHIVE_CRITERIA, $deletedCriteria);
        $this->assertNotContains('store_id', $deletedCriteria);
    }

    public function testGetStoreIdsWithPendingJobsReturnsDistinctStoreIds(): void
    {
        $whereClauses = [];
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(
            function (string $where) use (&$whereClauses, $select) {
                $whereClauses[] = $where;

                return $select;
            }
        );
        $select->method('order')->willReturnSelf();

        $dbAdapter = $this->createStub(AdapterInterface::class);
        $dbAdapter->method('select')->willReturn($select);
        $dbAdapter->method('fetchCol')->willReturn(['1', '2', '3']);

        $queue = $this->createObjectToTest(dbAdapter: $dbAdapter);

        $this->assertSame([1, 2, 3], $queue->getStoreIdsWithPendingJobs());
        $this->assertSame(['pid IS NULL', 'retries < max_retries', 'store_id IS NOT NULL'], $whereClauses);
    }

    public function testFailedJobInOneStoreSkipsOnlyThatStoresMoveIndex(): void
    {
        $objectManager = $this->createMockObjectManagerForIndexMover(1);
        $updateCalls = [];

        $jobs = [
            $this->createQueueJob(
                1,
                ProductIndexBuilder::class,
                'unauthorizedMethod',
                ['entityIds' => [1, 2]],
                1
            ),
            $this->createQueueJob(
                2,
                IndexMover::class,
                Queue::MOVE_INDEX_METHOD_NAME,
                ['tmpIndexName' => 'tmp_index', 'indexName' => 'prod_index'],
                1,
                $objectManager
            ),
            $this->createQueueJob(
                3,
                IndexMover::class,
                Queue::MOVE_INDEX_METHOD_NAME,
                ['tmpIndexName' => 'tmp_index', 'indexName' => 'prod_index'],
                2,
                $objectManager
            ),
        ];

        $queue = $this->createProcessingQueue($objectManager, $updateCalls, $jobs);

        $queue->runCron(10);

        $this->assertContains([['pid' => null], ['job_id = ?' => 2]], $updateCalls);
        $this->assertNotContains([['pid' => null], ['job_id = ?' => 3]], $updateCalls);
        $this->assertSame([1 => 1], $this->getPrivateProperty($queue, 'noOfFailedJobsByStore'));
    }

    public function testFailedJobWithoutStoreDoesNotAffectNumberedStores(): void
    {
        $objectManager = $this->createMockObjectManagerForIndexMover(2);
        $updateCalls = [];

        $jobs = [
            $this->createQueueJob(
                1,
                ProductIndexBuilder::class,
                'unauthorizedMethod',
                ['entityIds' => [1, 2]],
                null
            ),
            $this->createQueueJob(
                2,
                IndexMover::class,
                Queue::MOVE_INDEX_METHOD_NAME,
                ['tmpIndexName' => 'tmp_index', 'indexName' => 'prod_index'],
                1,
                $objectManager
            ),
            $this->createQueueJob(
                3,
                IndexMover::class,
                Queue::MOVE_INDEX_METHOD_NAME,
                ['tmpIndexName' => 'tmp_index', 'indexName' => 'prod_index'],
                2,
                $objectManager
            ),
        ];

        $queue = $this->createProcessingQueue($objectManager, $updateCalls, $jobs);

        $queue->runCron(10);

        $this->assertNotContains([['pid' => null], ['job_id = ?' => 2]], $updateCalls);
        $this->assertNotContains([['pid' => null], ['job_id = ?' => 3]], $updateCalls);
        $this->assertSame([0 => 1], $this->getPrivateProperty($queue, 'noOfFailedJobsByStore'));
    }

    public function testFailedJobStillSkipsSingleStoresMoveIndex(): void
    {
        $objectManager = $this->createMockObjectManagerForIndexMover(0);
        $updateCalls = [];

        $jobs = [
            $this->createQueueJob(
                1,
                ProductIndexBuilder::class,
                'unauthorizedMethod',
                ['entityIds' => [1, 2]],
                5
            ),
            $this->createQueueJob(
                2,
                IndexMover::class,
                Queue::MOVE_INDEX_METHOD_NAME,
                ['tmpIndexName' => 'tmp_index', 'indexName' => 'prod_index'],
                5,
                $objectManager
            ),
        ];

        $queue = $this->createProcessingQueue($objectManager, $updateCalls, $jobs);

        $queue->runCron(10);

        $this->assertContains([['pid' => null], ['job_id = ?' => 2]], $updateCalls);
        $this->assertSame([5 => 1], $this->getPrivateProperty($queue, 'noOfFailedJobsByStore'));
    }

    /**
     * ObjectManager mock returning a moveIndexWithSetSettings() mock handler for IndexMover,
     * with the given expectation on how often a handler is resolved.
     */
    private function createMockObjectManagerForIndexMover(int $times): ObjectManagerInterface
    {
        $handlerClass = IndexMover::class;
        $handler = $this->getMockBuilder($handlerClass)
            ->disableOriginalConstructor()
            ->onlyMethods(['moveIndexWithSetSettings'])
            ->getMock();
        $handler->expects($this->exactly($times))->method('moveIndexWithSetSettings');

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->exactly($times))
            ->method('get')
            ->with($handlerClass)
            ->willReturn($handler);

        return $objectManager;
    }

    /**
     * Queue wired for runCron() with actual job processing: the first claimed batch is empty
     * (full-reindex fetch) and the second returns the given jobs (realtime fetch).
     *
     * @param Job[] $jobs
     * @param array[] $updateCalls Bucket to receive [bind, where] recorded from AdapterInterface::update() calls
     */
    private function createProcessingQueue(
        ObjectManagerInterface $objectManager,
        array &$updateCalls,
        array $jobs,
    ): Queue {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(true);

        $itemsPerCall = [[], $jobs]; // [full reindex, realtime]
        $callIndex = 0;
        $collectionFactory = $this->createStub(JobCollectionFactory::class);
        $collectionFactory->method('create')->willReturnCallback(
            function () use ($itemsPerCall, &$callIndex) {
                $items = $itemsPerCall[$callIndex++] ?? [];

                $select = $this->createStub(Select::class);
                $select->method('limit')->willReturnSelf();
                $select->method('forUpdate')->willReturnSelf();

                $collection = $this->createStub(Collection::class);
                $collection->method('addFieldToFilter')->willReturnSelf();
                $collection->method('setOrder')->willReturnSelf();
                $collection->method('getSelect')->willReturn($select);
                $collection->method('getItems')->willReturn($items);

                return $collection;
            }
        );

        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        $dbAdapter = $this->createStub(AdapterInterface::class);
        $dbAdapter->method('select')->willReturn($select);
        $dbAdapter->method('query')->willReturn($this->createStub(Zend_Db_Statement_Interface::class));
        $dbAdapter->method('update')->willReturnCallback(
            function (string $table, array $bind, $where = '') use (&$updateCalls) {
                $updateCalls[] = [$bind, $where];

                return 1;
            }
        );

        return $this->createObjectToTest(
            configHelper: $configHelper,
            objectManager: $objectManager,
            dbAdapter: $dbAdapter,
            jobCollectionFactory: $collectionFactory,
        );
    }

    /**
     * Real Job model mirroring a claimed queue row: store comes from the store_id column,
     * Job::prepare() later decodes $decodedData for execution.
     */
    private function createQueueJob(
        int $id,
        string $class,
        string $method,
        array $decodedData,
        ?int $storeId,
        ?ObjectManagerInterface $jobObjectManager = null,
    ): Job {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        $resourceModel = $this->createStub(JobResourceModel::class);
        $resourceModel->method('save')->willReturnSelf();

        $job = new Job(
            $context,
            $this->createStub(Registry::class),
            $jobObjectManager ?? $this->createStub(ObjectManagerInterface::class),
            $resourceModel,
        );

        $job->setData('job_id', $id);
        $job->setIdFieldName('job_id');
        $job->setClass($class);
        $job->setMethod($method);
        $job->setData('data', json_encode($decodedData));
        $job->setData('data_size', 1);

        if ($storeId !== null) {
            $job->setData('store_id', $storeId);
        }

        return $job;
    }

    /**
     * Payloads keep the legacy shape (store inside the payload): the deprecated addToQueue()
     * shell spreads $data as-is when the queue is inactive, preserving legacy direct-execution
     * semantics. Storeless shapes are covered by the addJob() tests.
     */
    public static function authorizedHandlersProvider(): array
    {
        return [
            'IndicesConfigurator::saveConfigurationToAlgolia' => [
                'class' => 'Algolia\AlgoliaSearch\Model\IndicesConfigurator',
                'method' => 'saveConfigurationToAlgolia',
                'data' => ['storeId' => 1, 'useTmpIndex' => false, 'filteredEntities' => ['products']],
            ],
            'IndexMover::moveIndexWithSetSettings' => [
                'class' => 'Algolia\AlgoliaSearch\Model\IndexMover',
                'method' => 'moveIndexWithSetSettings',
                'data' => ['tmpIndexName' => 'test_index_tmp', 'indexName' => 'test_index', 'storeId' => 1],
            ],
            'Product\IndexBuilder::buildIndex' => [
                'class' => ProductIndexBuilder::class,
                'method' => 'buildIndex',
                'data' => ['storeId' => 1, 'entityIds' => [1, 2, 3], 'options' => []],
            ],
            'Category\IndexBuilder::buildIndexList' => [
                'class' => CategoryIndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => ['storeId' => 1, 'entityIds' => [1, 2, 3], 'options' => []],
            ],
        ];
    }

    public static function unauthorizedHandlersProvider(): array
    {
        return [
            'unauthorized class' => [
                'class' => 'Algolia\AlgoliaSearch\Dummy\UnauthorizedClass',
                'method' => 'buildIndex',
            ],
            'unauthorized method' => [
                'class' => ProductIndexBuilder::class,
                'method' => 'unauthorizedMethod',
            ],
            'method from different class whitelist' => [
                'class' => CategoryIndexBuilder::class,
                'method' => 'deleteInactiveProducts',
            ],
        ];
    }
}
