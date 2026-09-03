<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Model\Job;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job\CollectionFactory as JobCollectionFactory;
use Algolia\AlgoliaSearch\Service\Category\IndexBuilder as CategoryIndexBuilder;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder as ProductIndexBuilder;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\ConsoleOutput;

class QueueTest extends TestCase
{
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
    ): Queue {
        $dbAdapter ??= $this->createStub(AdapterInterface::class);

        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getTableName')->willReturnArgument(0);
        $resourceConnection->method('getConnection')->willReturn($dbAdapter);

        $objectManager ??= $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('create')->willReturn($resourceConnection);

        return new Queue(
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $logger ?? $this->createStub(DiagnosticsLogger::class),
            $this->createStub(JobCollectionFactory::class),
            $resourceConnection,
            $objectManager,
            $this->createStub(ConsoleOutput::class),
        );
    }

    #[DataProvider('authorizedHandlersProvider')]
    public function testAddToQueueSucceedsForAuthorizedHandlerWhenQueueInactive(string $class, string $method, array $data): void
    {
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

        $queue->addToQueue($class, $method, $data);
    }

    #[DataProvider('authorizedHandlersProvider')]
    public function testAddToQueueSucceedsForAuthorizedHandlerWhenQueueActive(string $class, string $method, array $data): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(true);

        $dbAdapter = $this->createMock(AdapterInterface::class);
        $dbAdapter->expects($this->once())->method('insert');

        $queue = $this->createObjectToTest(configHelper: $configHelper, dbAdapter: $dbAdapter);

        $queue->addToQueue($class, $method, $data);
    }

    #[DataProvider('unauthorizedHandlersProvider')]
    public function testAddToQueueThrowsForUnauthorizedHandlersWhenQueueInactive(string $class, string $method): void
    {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(false);

        $queue = $this->createObjectToTest(configHelper: $configHelper);

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Unauthorized job handler');

        $queue->addToQueue($class, $method, []);
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

        $queue->addToQueue($class, $method, []);
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
                'data' => ['test_index_tmp', 'test_index', 1],
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
}
