<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Model\Job;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\Category\IndexBuilder as CategoryIndexBuilder;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder as ProductIndexBuilder;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Output\ConsoleOutput;

class QueueTest extends TestCase
{
    private null|(ConfigHelper&MockObject) $configHelper = null;
    private null|(DiagnosticsLogger&MockObject) $logger = null;
    private null|(ObjectManagerInterface&MockObject) $objectManager = null;
    private null|(AdapterInterface&MockObject) $dbAdapter = null;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->logger = $this->createMock(DiagnosticsLogger::class);
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->dbAdapter = $this->createMock(AdapterInterface::class);
    }

    public function testAddToQueueInsertsJobWhenQueueIsActive(): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(true);
        $this->configHelper->method('getRetryLimit')->willReturn(3);
        $this->configHelper->method('isEnhancedQueueArchiveEnabled')->willReturn(false);

        $this->dbAdapter->expects($this->once())
            ->method('insert')
            ->with(
                'algoliasearch_queue',
                $this->callback(function ($data) {
                    return $data['class'] === ProductIndexBuilder::class
                        && $data['method'] === 'buildIndex'
                        && $data['max_retries'] === 3;
                })
            );

        $queue = $this->createQueue();
        $queue->addToQueue(
            ProductIndexBuilder::class,
            'buildIndex',
            ['storeId' => 1, 'entityIds' => [1, 2, 3]],
            3
        );
    }

    public function testAddToQueueExecutesImmediatelyWhenQueueIsInactive(): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(false);

        $mockHandler = $this->getMockBuilder(ProductIndexBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['buildIndex'])
            ->getMock();

        $mockHandler->expects($this->once())
            ->method('buildIndex')
            ->with(1, [1, 2, 3], []);

        $this->objectManager->expects($this->once())
            ->method('get')
            ->with(ProductIndexBuilder::class)
            ->willReturn($mockHandler);

        $queue = $this->createQueue();
        $queue->addToQueue(
            ProductIndexBuilder::class,
            'buildIndex',
            [1, [1, 2, 3], []]
        );
    }

    public function testAddToQueueThrowsExceptionForUnauthorizedClassWhenQueueInactive(): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(false);

        $queue = $this->createQueue();

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Unauthorized job handler');

        $queue->addToQueue(
            'Algolia\AlgoliaSearch\Dummy\UnauthorizedClass',
            'buildIndex',
            ['storeId' => 1]
        );
    }

    public function testAddToQueueThrowsExceptionForUnauthorizedMethodWhenQueueInactive(): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(false);

        $queue = $this->createQueue();

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Unauthorized job handler');

        $queue->addToQueue(
            CategoryIndexBuilder::class,
            'unauthorizedMethod',
            ['storeId' => 1]
        );
    }

    public function testAddToQueueThrowsExceptionForMethodNotInClassWhitelistWhenQueueInactive(): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(false);

        $queue = $this->createQueue();

        // 'deleteInactiveProducts' is only allowed for Product\IndexBuilder, not Category\IndexBuilder
        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Unauthorized job handler');

        $queue->addToQueue(
            CategoryIndexBuilder::class,
            'deleteInactiveProducts',
            ['storeId' => 1]
        );
    }

    /**
     * @dataProvider authorizedHandlersProvider
     */
    public function testAddToQueueSucceedsForAuthorizedHandlerWhenQueueInactive(string $class, string $method, array $data): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(false);

        $mockHandler = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods([$method])
            ->getMock();

        $mockHandler->expects($this->once())->method($method);

        $this->objectManager->expects($this->once())
            ->method('get')
            ->with($class)
            ->willReturn($mockHandler);

        $queue = $this->createQueue();
        $queue->addToQueue($class, $method, $data);
    }

    /**
     * @dataProvider unauthorizedHandlersProvider
     */
    public function testAddToQueueThrowsForUnauthorizedHandlersWhenQueueInactive(string $class, string $method): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(false);

        $queue = $this->createQueue();

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Unauthorized job handler');

        $queue->addToQueue($class, $method, []);
    }

    public function testAddToQueueThrowsExceptionForUnauthorizedClassWhenQueueActive(): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(true);

        // Unauthorized jobs should be rejected at queue time, not just at execution time
        $this->dbAdapter->expects($this->never())->method('insert');

        $queue = $this->createQueue();

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Unauthorized job handler');

        $queue->addToQueue(
            'Algolia\AlgoliaSearch\Dummy\UnauthorizedClass',
            'someMethod',
            ['storeId' => 1]
        );
    }

    /** Unauthorized jobs should be rejected at queue time, not just at execution time */
    public function testAddToQueueThrowsExceptionForUnauthorizedMethodWhenQueueActive(): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(true);

        $this->dbAdapter->expects($this->never())->method('insert');

        $queue = $this->createQueue();

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Unauthorized job handler');

        $queue->addToQueue(
            ProductIndexBuilder::class,
            'unauthorizedMethod',
            ['storeId' => 1]
        );
    }

    public function testAddToQueueReferencesJobAllowedHandlers(): void
    {
        // Verify Queue uses the same whitelist as Job
        $this->assertNotEmpty(Job::ALLOWED_HANDLERS);
        $this->assertArrayHasKey(ProductIndexBuilder::class, Job::ALLOWED_HANDLERS);
        $this->assertContains('buildIndex', Job::ALLOWED_HANDLERS[ProductIndexBuilder::class]);
    }

    public static function authorizedHandlersProvider(): array
    {
        return [
            'IndicesConfigurator::saveConfigurationToAlgolia' => [
                'class' => 'Algolia\AlgoliaSearch\Model\IndicesConfigurator',
                'method' => 'saveConfigurationToAlgolia',
                'data' => [1, false],
            ],
            'IndexMover::moveIndexWithSetSettings' => [
                'class' => 'Algolia\AlgoliaSearch\Model\IndexMover',
                'method' => 'moveIndexWithSetSettings',
                'data' => ['tmp_index', 'test_index', 1],
            ],
            'Product\IndexBuilder::buildIndex' => [
                'class' => ProductIndexBuilder::class,
                'method' => 'buildIndex',
                'data' => [1, [1, 2, 3], []],
            ],
            'Category\IndexBuilder::buildIndexList' => [
                'class' => CategoryIndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => [1, [1, 2, 3], []],
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

    private function createQueue(): Queue
    {
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getTableName')
            ->willReturnCallback(fn($table) => $table);

        $mockResourceConnection = $this->createMock(ResourceConnection::class);
        $mockResourceConnection->method('getConnection')->willReturn($this->dbAdapter);

        $this->objectManager->method('create')
            ->with(ResourceConnection::class)
            ->willReturn($mockResourceConnection);

        $output = $this->createMock(ConsoleOutput::class);

        // Create Queue using reflection to bypass the generated CollectionFactory dependency
        $queue = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        // Inject dependencies using reflection
        $this->setPrivateProperty($queue, 'configHelper', $this->configHelper);
        $this->setPrivateProperty($queue, 'logger', $this->logger);
        $this->setPrivateProperty($queue, 'objectManager', $this->objectManager);
        $this->setPrivateProperty($queue, 'output', $output);
        $this->setPrivateProperty($queue, 'table', 'algoliasearch_queue');
        $this->setPrivateProperty($queue, 'logTable', 'algoliasearch_queue_log');
        $this->setPrivateProperty($queue, 'archiveTable', 'algoliasearch_queue_archive');
        $this->setPrivateProperty($queue, 'db', $this->dbAdapter);

        return $queue;
    }
}
