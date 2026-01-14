<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Model\Job;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResourceModel;
use Algolia\AlgoliaSearch\Service\Category\IndexBuilder as CategoryIndexBuilder;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder as ProductIndexBuilder;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use PHPUnit\Framework\MockObject\MockObject;

class JobTest extends \PHPUnit\Framework\TestCase
{
    private null|(Context&MockObject) $context = null;
    private null|(Registry&MockObject) $registry = null;
    private null|(ObjectManagerInterface&MockObject) $objectManager = null;
    private null|(JobResourceModel&MockObject) $resourceModel = null;

    private ?Job $job = null;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $eventDispatcher = $this->createMock(ManagerInterface::class);
        $this->context->method('getEventDispatcher')->willReturn($eventDispatcher);

        $this->registry = $this->createMock(Registry::class);
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->resourceModel = $this->createMock(JobResourceModel::class);

        $this->job = new Job(
            $this->context,
            $this->registry,
            $this->objectManager,
            $this->resourceModel
        );
    }

    /**
     * @dataProvider authorizedHandlersProvider
     */
    public function testExecuteSucceedsForAuthorizedHandler(string $class, string $method, array $methodArgs): void
    {
        $this->job->setClass($class);
        $this->job->setMethod($method);
        $this->job->setData('decoded_data', $methodArgs);
        $this->job->setData('retries', 0);

        $mockHandler = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods([$method])
            ->getMock();

        $mockHandler->expects($this->once())
            ->method($method);

        $this->objectManager->expects($this->once())
            ->method('get')
            ->with($class)
            ->willReturn($mockHandler);

        $this->resourceModel->method('save')->willReturnSelf();

        $result = $this->job->execute();

        $this->assertSame($this->job, $result);
        $this->assertEquals(1, $this->job->getData('retries'));
    }

    /**
     * @dataProvider unauthorizedHandlersProvider
     */
    public function testExecuteThrowsForUnauthorizedHandlers(string $class, string $method): void
    {
        $this->job->setClass($class);
        $this->job->setMethod($method);
        $this->job->setData('decoded_data', []);

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Unauthorized job handler');

        $this->job->execute();
    }

    public static function authorizedHandlersProvider(): array
    {
        return [
            'IndicesConfigurator::saveConfigurationToAlgolia' => [
                'class' => 'Algolia\AlgoliaSearch\Model\IndicesConfigurator',
                'method' => 'saveConfigurationToAlgolia',
                'methodArgs' => [1, false],
            ],
            'IndexMover::moveIndexWithSetSettings' => [
                'class' => 'Algolia\AlgoliaSearch\Model\IndexMover',
                'method' => 'moveIndexWithSetSettings',
                'methodArgs' => ['test_index_tmp', 'test_index', 1],
            ],
            'Product\IndexBuilder::buildIndex' => [
                'class' => ProductIndexBuilder::class,
                'method' => 'buildIndex',
                'methodArgs' => [1, [1, 2, 3], []],
            ],
            'Product\IndexBuilder::buildIndexFull' => [
                'class' => ProductIndexBuilder::class,
                'method' => 'buildIndexFull',
                'methodArgs' => [1, []],
            ],
            'Product\IndexBuilder::buildIndexList' => [
                'class' => ProductIndexBuilder::class,
                'method' => 'buildIndexList',
                'methodArgs' => [1, [1, 2, 3], []],
            ],
            'Product\IndexBuilder::deleteInactiveProducts' => [
                'class' => ProductIndexBuilder::class,
                'method' => 'deleteInactiveProducts',
                'methodArgs' => [1],
            ],
            'Category\IndexBuilder::buildIndex' => [
                'class' => CategoryIndexBuilder::class,
                'method' => 'buildIndex',
                'methodArgs' => [1, [1, 2, 3], []],
            ],
            'Category\IndexBuilder::buildIndexFull' => [
                'class' => CategoryIndexBuilder::class,
                'method' => 'buildIndexFull',
                'methodArgs' => [1, []],
            ],
            'Category\IndexBuilder::buildIndexList' => [
                'class' => CategoryIndexBuilder::class,
                'method' => 'buildIndexList',
                'methodArgs' => [1, [1, 2, 3], []],
            ],
            'Page\IndexBuilder::buildIndex' => [
                'class' => 'Algolia\AlgoliaSearch\Service\Page\IndexBuilder',
                'method' => 'buildIndex',
                'methodArgs' => [1, [1, 2, 3], []],
            ],
            'Suggestion\IndexBuilder::buildIndexFull' => [
                'class' => 'Algolia\AlgoliaSearch\Service\Suggestion\IndexBuilder',
                'method' => 'buildIndexFull',
                'methodArgs' => [1, []],
            ],
            'AdditionalSection\IndexBuilder::buildIndex' => [
                'class' => 'Algolia\AlgoliaSearch\Service\AdditionalSection\IndexBuilder',
                'method' => 'buildIndex',
                'methodArgs' => [1, [1, 2, 3], []],
            ],
        ];
    }

    public static function unauthorizedHandlersProvider(): array
    {
        return [
            'completely unauthorized class' => [
                'class' => 'Algolia\AlgoliaSearch\Dummy\UnauthorizedClass',
                'method' => 'buildIndex',
            ],
            'unauthorized method on authorized class' => [
                'class' => ProductIndexBuilder::class,
                'method' => 'unauthorizedMethod',
            ],
            'method from different class whitelist' => [
                'class' => CategoryIndexBuilder::class,
                'method' => 'deleteInactiveProducts',
            ],
            'empty class' => [
                'class' => '',
                'method' => 'buildIndex',
            ],
            'empty method' => [
                'class' => ProductIndexBuilder::class,
                'method' => '',
            ],
        ];
    }
}

