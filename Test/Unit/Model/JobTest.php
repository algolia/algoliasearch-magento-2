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
use PHPUnit\Framework\Attributes\DataProvider;

class JobTest extends TestCase
{
    protected function createObjectToTest(
        ?ObjectManagerInterface $objectManager = null,
        ?JobResourceModel $resourceModel = null,
    ): Job {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        return new Job(
            $context,
            $this->createStub(Registry::class),
            $objectManager ?? $this->createStub(ObjectManagerInterface::class),
            $resourceModel ?? $this->createStub(JobResourceModel::class),
        );
    }

    #[DataProvider('authorizedHandlersProvider')]
    public function testExecuteSucceedsForAuthorizedHandler(string $class, string $method, array $methodArgs): void
    {
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

        $resourceModel = $this->createStub(JobResourceModel::class);
        $resourceModel->method('save')->willReturnSelf();

        $job = $this->createObjectToTest(objectManager: $objectManager, resourceModel: $resourceModel);
        $job->setClass($class);
        $job->setMethod($method);
        $job->setData('decoded_data', $methodArgs);
        $job->setData('retries', 0);

        $result = $job->execute();

        $this->assertSame($job, $result);
        $this->assertEquals(1, $job->getData('retries'));
    }

    #[DataProvider('unauthorizedHandlersProvider')]
    public function testExecuteThrowsForUnauthorizedHandlers(string $class, string $method): void
    {
        $job = $this->createObjectToTest();
        $job->setClass($class);
        $job->setMethod($method);
        $job->setData('decoded_data', []);

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Unauthorized job handler');

        $job->execute();
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
