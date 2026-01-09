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
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class QueueTest extends \PHPUnit\Framework\TestCase
{
    private null|(ConfigHelper&MockObject) $configHelper = null;
    private null|(DiagnosticsLogger&MockObject) $logger = null;
    private null|(ObjectManagerInterface&MockObject) $objectManager = null;
    private null|(AdapterInterface&MockObject) $dbAdapter = null;
    private ?Queue $queue = null;

    /**
     * @throws \ReflectionException
     */
    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->logger = $this->createMock(DiagnosticsLogger::class);
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->dbAdapter = $this->createMock(AdapterInterface::class);

        $this->queue = $this->createQueueMock();
    }

    /**
     * Immediately process the valid method
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

        $this->queue->addToQueue($class, $method, $data);
    }

    /**
     * Queue the valid method
     * @dataProvider authorizedHandlersProvider
     */
    public function testAddToQueueSucceedsForAuthorizedHandlerWhenQueueActive(string $class, string $method, array $data): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(true);

        $this->dbAdapter->expects($this->once())->method('insert');

        $this->queue->addToQueue($class, $method, $data);
    }

    /**
     * Unauthorized jobs should be rejected at execution time when queue is inactive
     * @dataProvider unauthorizedHandlersProvider
     */
    public function testAddToQueueThrowsForUnauthorizedHandlersWhenQueueInactive(string $class, string $method): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(false);

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Unauthorized job handler');

        $this->queue->addToQueue($class, $method, []);
    }

    /**
     * Unauthorized jobs should be rejected at queue time, not just at execution time
     * @dataProvider unauthorizedHandlersProvider
     */
    public function testAddToQueueThrowsForUnauthorizedHandlersWhenQueueActive(string $class, string $method): void
    {
        $this->configHelper->method('isQueueActive')->willReturn(true);

        $this->dbAdapter->expects($this->never())->method('insert');

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Unauthorized job handler');

        $this->queue->addToQueue($class, $method, []);
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

    /**
     * Create Queue using reflection to bypass the generated CollectionFactory dependency
     * @throws \ReflectionException
     */
    private function createQueueMock(): Queue
    {

        $queue = $this->getMockBuilder(Queue::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->setPrivateProperty($queue, 'configHelper', $this->configHelper);
        $this->setPrivateProperty($queue, 'logger', $this->logger);
        $this->setPrivateProperty($queue, 'objectManager', $this->objectManager);
        $this->setPrivateProperty($queue, 'db', $this->dbAdapter);

        return $queue;
    }

    /**
     * Set a private property of a class
     *
     * @param object $obj The object to set the property for
     * @param string $prop The name of the property to set
     * @param mixed $value The value to set the property to
     *
     * @throws \ReflectionException
     */
    protected function setPrivateProperty(object $obj, string $prop, mixed $value): void
    {
        $ref = new \ReflectionClass($obj);
        $p = $ref->getProperty($prop);
        $p->setValue($obj, $value);
    }

}
