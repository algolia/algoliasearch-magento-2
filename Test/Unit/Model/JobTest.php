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
    public function testExecuteSucceedsForAuthorizedHandler(string $class, string $method, array $methodArgs, int $storeId): void
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
        $job->setData('store_id', $storeId);

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

    /**
     * Runs a job through execute() against a mock handler whose received arguments are
     * captured into $receivedArgs (named-argument keys are aligned positionally).
     */
    private function createHandlerCaptureJob(
        string $class,
        string $method,
        array $decodedData,
        ?int $storeId,
        array &$receivedArgs
    ): Job {
        $mockHandler = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods([$method])
            ->getMock();
        $mockHandler->expects($this->once())
            ->method($method)
            ->willReturnCallback(function (...$args) use (&$receivedArgs) {
                $receivedArgs = $args;
            });

        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn($mockHandler);

        $resourceModel = $this->createStub(JobResourceModel::class);
        $resourceModel->method('save')->willReturnSelf();

        $job = $this->createObjectToTest(objectManager: $objectManager, resourceModel: $resourceModel);
        $job->setClass($class);
        $job->setMethod($method);
        $job->setData('decoded_data', $decodedData);
        $job->setData('retries', 0);

        if ($storeId !== null) {
            $job->setData('store_id', $storeId);
        }

        return $job;
    }

    public function testExecuteSuppliesStoreIdFromColumn(): void
    {
        $receivedArgs = [];
        $job = $this->createHandlerCaptureJob(
            ProductIndexBuilder::class,
            'buildIndex',
            ['entityIds' => [1, 2, 3], 'options' => []],
            3,
            $receivedArgs
        );

        $job->execute();

        // Named arguments land positionally in the mock's declared signature:
        // buildIndex(int $storeId, ?array $entityIds, ?array $options)
        $this->assertSame(3, $receivedArgs[0]);
        $this->assertSame([1, 2, 3], $receivedArgs[1]);
        $this->assertSame([], $receivedArgs[2]);
    }

    public function testExecutePrefersColumnOverDisagreeingPayloadStore(): void
    {
        $receivedArgs = [];
        $job = $this->createHandlerCaptureJob(
            ProductIndexBuilder::class,
            'buildIndex',
            ['entityIds' => [1, 2, 3], 'options' => [], 'storeId' => 5],
            3,
            $receivedArgs
        );

        $job->execute();

        $this->assertSame(3, $receivedArgs[0]);
    }

    public function testExecuteKeepsPayloadStoreForLegacyRowWithoutColumn(): void
    {
        $receivedArgs = [];
        $job = $this->createHandlerCaptureJob(
            ProductIndexBuilder::class,
            'buildIndex',
            ['entityIds' => [1, 2, 3], 'options' => [], 'storeId' => 2],
            null,
            $receivedArgs
        );

        $job->execute();

        $this->assertSame(2, $receivedArgs[0]);
    }

    public function testPreparePromotesPayloadStoreOnlyWhenColumnEmpty(): void
    {
        $job = $this->createObjectToTest();
        $job->setData('data', json_encode(['entityIds' => [1], 'storeId' => 2]));

        $job->prepare();

        $this->assertSame(2, $job->getStoreId());
    }

    public function testPrepareKeepsColumnStoreOverPayloadStore(): void
    {
        $job = $this->createObjectToTest();
        $job->setData('store_id', 3);
        $job->setData('data', json_encode(['entityIds' => [1], 'storeId' => 2]));

        $job->prepare();

        $this->assertSame(3, $job->getStoreId());
    }

    public function testJobsMergeOnlyWithinSameStore(): void
    {
        $jobA = $this->createStoreScopedJob(1, ['entityIds' => [1, 2]]);
        $jobB = $this->createStoreScopedJob(1, ['entityIds' => [2, 3]]);
        $jobC = $this->createStoreScopedJob(2, ['entityIds' => [4]]);

        $this->assertTrue($jobA->canMerge($jobB, 10));
        $this->assertFalse($jobA->canMerge($jobC, 10));

        $jobA->merge($jobB);

        $this->assertSame([1, 2, 3], array_values($jobA->getDecodedData()['entityIds']));
        $this->assertSame(3, $jobA->getDataSize());
    }

    private function createStoreScopedJob(int $storeId, array $decodedData): Job
    {
        $job = $this->createObjectToTest();
        $job->setData('job_id', random_int(1, PHP_INT_MAX));
        $job->setIdFieldName('job_id');
        $job->setClass(ProductIndexBuilder::class);
        $job->setMethod('buildIndex');
        $job->setData('store_id', $storeId);
        $job->setData('decoded_data', $decodedData);
        $job->setData('data_size', count($decodedData['entityIds']));
        $job->prepare();

        return $job;
    }

    public static function authorizedHandlersProvider(): array
    {
        return [
            'IndicesConfigurator::saveConfigurationToAlgolia' => [
                'class' => 'Algolia\AlgoliaSearch\Model\IndicesConfigurator',
                'method' => 'saveConfigurationToAlgolia',
                'methodArgs' => ['useTmpIndex' => false, 'filteredEntities' => ['products']],
                'storeId' => 1,
            ],
            'IndexMover::moveIndexWithSetSettings' => [
                'class' => 'Algolia\AlgoliaSearch\Model\IndexMover',
                'method' => 'moveIndexWithSetSettings',
                'methodArgs' => ['tmpIndexName' => 'test_index_tmp', 'indexName' => 'test_index'],
                'storeId' => 1,
            ],
            'Product\IndexBuilder::buildIndex' => [
                'class' => ProductIndexBuilder::class,
                'method' => 'buildIndex',
                'methodArgs' => ['entityIds' => [1, 2, 3], 'options' => []],
                'storeId' => 1,
            ],
            'Product\IndexBuilder::buildIndexFull' => [
                'class' => ProductIndexBuilder::class,
                'method' => 'buildIndexFull',
                'methodArgs' => ['options' => []],
                'storeId' => 1,
            ],
            'Product\IndexBuilder::buildIndexList' => [
                'class' => ProductIndexBuilder::class,
                'method' => 'buildIndexList',
                'methodArgs' => ['entityIds' => [1, 2, 3], 'options' => []],
                'storeId' => 1,
            ],
            'Product\IndexBuilder::deleteInactiveProducts' => [
                'class' => ProductIndexBuilder::class,
                'method' => 'deleteInactiveProducts',
                'methodArgs' => [],
                'storeId' => 1,
            ],
            'Category\IndexBuilder::buildIndex' => [
                'class' => CategoryIndexBuilder::class,
                'method' => 'buildIndex',
                'methodArgs' => ['entityIds' => [1, 2, 3], 'options' => []],
                'storeId' => 1,
            ],
            'Category\IndexBuilder::buildIndexFull' => [
                'class' => CategoryIndexBuilder::class,
                'method' => 'buildIndexFull',
                'methodArgs' => ['options' => []],
                'storeId' => 1,
            ],
            'Category\IndexBuilder::buildIndexList' => [
                'class' => CategoryIndexBuilder::class,
                'method' => 'buildIndexList',
                'methodArgs' => ['entityIds' => [1, 2, 3], 'options' => []],
                'storeId' => 1,
            ],
            'Page\IndexBuilder::buildIndex' => [
                'class' => 'Algolia\AlgoliaSearch\Service\Page\IndexBuilder',
                'method' => 'buildIndex',
                'methodArgs' => ['entityIds' => [1, 2, 3], 'options' => []],
                'storeId' => 1,
            ],
            'Suggestion\IndexBuilder::buildIndexFull' => [
                'class' => 'Algolia\AlgoliaSearch\Service\Suggestion\IndexBuilder',
                'method' => 'buildIndexFull',
                'methodArgs' => ['options' => []],
                'storeId' => 1,
            ],
            'AdditionalSection\IndexBuilder::buildIndex' => [
                'class' => 'Algolia\AlgoliaSearch\Service\AdditionalSection\IndexBuilder',
                'method' => 'buildIndex',
                'methodArgs' => ['entityIds' => [1, 2, 3], 'options' => []],
                'storeId' => 1,
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
