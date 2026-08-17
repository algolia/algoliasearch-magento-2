<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Index;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterfaceFactory;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\Index\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\Index\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Exception\NoSuchEntityException;

class IndexOptionsBuilderTest extends TestCase
{
    protected function createObjectToTest(
        ?IndexNameFetcher $indexNameFetcher = null,
        ?IndexOptionsInterfaceFactory $indexOptionsInterfaceFactory = null,
        ?DiagnosticsLogger $logger = null,
    ): IndexOptionsBuilder {
        return new IndexOptionsBuilder(
            $indexNameFetcher ?? $this->createStub(IndexNameFetcher::class),
            $indexOptionsInterfaceFactory ?? $this->createStub(IndexOptionsInterfaceFactory::class),
            $logger ?? $this->createStub(DiagnosticsLogger::class),
        );
    }

    /**
     * Use actual implementation for testing instead of a mock in order to maintain state
     */
    private function createIndexOptionsMock(
        ?string $indexName = null,
        ?int $storeId = null,
        ?string $indexSuffix = null,
        bool $isTmp = false
    ): IndexOptionsInterface {
        return new \Algolia\AlgoliaSearch\Model\Data\IndexOptions([
            IndexOptionsInterface::INDEX_NAME => $indexName,
            IndexOptionsInterface::STORE_ID => $storeId,
            IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
            IndexOptionsInterface::IS_TMP => $isTmp,
        ]);
    }

    private function createIndexOptionsFactoryMock(
        IndexOptionsInterface $indexOptions,
        array $expectedData
    ): IndexOptionsInterfaceFactory {
        $factory = $this->createMock(IndexOptionsInterfaceFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with(['data' => $expectedData])
            ->willReturn($indexOptions);

        return $factory;
    }

    public function testBuildWithComputedIndexWithAllParameters(): void
    {
        $indexSuffix = '_products';
        $storeId = 1;
        $isTmp = true;
        $computedIndexName = 'magento2_default_products_tmp';

        $indexOptions = $this->createIndexOptionsMock(null, $storeId, $indexSuffix, $isTmp);
        $factory = $this->createIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::STORE_ID => $storeId,
            IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
            IndexOptionsInterface::IS_TMP => $isTmp,
        ]);

        $indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $indexNameFetcher->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willReturn($computedIndexName);

        $indexOptionsBuilder = $this->createObjectToTest($indexNameFetcher, $factory);

        $result = $indexOptionsBuilder->buildWithComputedIndex($indexSuffix, $storeId, $isTmp);

        $this->assertEquals($computedIndexName, $result->getIndexName());
    }

    /**
     * Suffix must always be supplied with a computed index
     */
    public function testBuildWithComputedIndexWithNullParameters(): void
    {
        $factory = $this->createMock(IndexOptionsInterfaceFactory::class);
        $factory->expects($this->never())->method('create');

        $logger = $this->createMock(DiagnosticsLogger::class);
        $logger->expects($this->never())->method('error');

        $indexOptionsBuilder = $this->createObjectToTest(indexOptionsInterfaceFactory: $factory, logger: $logger);

        $this->expectException(\ArgumentCountError::class);
        $this->expectExceptionMessageMatches('/^Too few arguments to function/');

        $indexOptionsBuilder->buildWithComputedIndex();
    }

    public function testBuildWithComputedIndexWithIndexNameFetcherException(): void
    {
        $indexSuffix = '_products';
        $storeId = 1;
        $isTmp = false;

        $indexOptions = $this->createIndexOptionsMock(null, $storeId, $indexSuffix, $isTmp);

        $factory = $this->createIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::STORE_ID => $storeId,
            IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
            IndexOptionsInterface::IS_TMP => $isTmp,
        ]);

        $indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $indexNameFetcher->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willThrowException(new NoSuchEntityException(__('Store not found')));

        $indexOptionsBuilder = $this->createObjectToTest($indexNameFetcher, $factory);

        $this->expectException(NoSuchEntityException::class);

        $indexOptionsBuilder->buildWithComputedIndex($indexSuffix, $storeId, $isTmp);
    }

    public function testBuildWithEnforcedIndexWithAllParameters(): void
    {
        $indexName = 'custom_index_name';
        $storeId = 2;

        $indexOptions = $this->createIndexOptionsMock($indexName, $storeId);

        $factory = $this->createIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::INDEX_NAME => $indexName,
            IndexOptionsInterface::STORE_ID => $storeId,
        ]);

        $indexOptionsBuilder = $this->createObjectToTest(indexOptionsInterfaceFactory: $factory);

        $result = $indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->assertEquals($indexName, $result->getIndexName());
    }

    public function testBuildWithEnforcedIndexWithNullParameters(): void
    {
        $indexOptions = $this->createIndexOptionsMock(null, null);

        $factory = $this->createIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::INDEX_NAME => null,
            IndexOptionsInterface::STORE_ID => null,
        ]);

        $indexOptionsBuilder = $this->createObjectToTest(indexOptionsInterfaceFactory: $factory);

        $result = $indexOptionsBuilder->buildWithEnforcedIndex();

        $this->assertEquals('', $result->getIndexName()); // Should this throw an exception?
    }

    public function testComputeIndexNameWithEnforcedIndexName(): void
    {
        $enforcedIndexName = 'enforced_index_name';
        $indexOptions = $this->createIndexOptionsMock($enforcedIndexName);

        $indexOptionsBuilder = $this->createObjectToTest();

        $result = $this->invokeMethod(
            $indexOptionsBuilder,
            'computeIndexName',
            [$indexOptions]
        );

        $this->assertEquals($enforcedIndexName, $result);
    }

    public function testComputeIndexNameWithValidSuffix(): void
    {
        $indexSuffix = '_products';
        $storeId = 1;
        $isTmp = false;
        $computedIndexName = 'magento2_default_products';

        $indexOptions = $this->createIndexOptionsMock(null, $storeId, $indexSuffix, $isTmp);

        $indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $indexNameFetcher->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willReturn($computedIndexName);

        $indexOptionsBuilder = $this->createObjectToTest($indexNameFetcher);

        $result = $this->invokeMethod(
            $indexOptionsBuilder,
            'computeIndexName',
            [$indexOptions]
        );

        $this->assertEquals($computedIndexName, $result);
    }

    public function testComputeIndexNameWithNullSuffixThrowsException(): void
    {
        $storeId = 1;
        $isTmp = false;
        $indexOptions = $this->createIndexOptionsMock(null, $storeId, null, $isTmp);

        $logger = $this->createMock(DiagnosticsLogger::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Index name could not be computed due to missing suffix.',
                [
                    'storeId' => $storeId,
                    'isTmp' => $isTmp,
                ]
            );

        $indexOptionsBuilder = $this->createObjectToTest(logger: $logger);

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Index name could not be computed due to missing suffix.');

        $this->invokeMethod(
            $indexOptionsBuilder,
            'computeIndexName',
            [$indexOptions]
        );
    }

    public function testComputeIndexNameWithIndexNameFetcherException(): void
    {
        $indexSuffix = '_products';
        $storeId = 1;
        $isTmp = false;

        $indexOptions = $this->createIndexOptionsMock(null, $storeId, $indexSuffix, $isTmp);

        $indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $indexNameFetcher->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willThrowException(new NoSuchEntityException(__('Store not found')));

        $indexOptionsBuilder = $this->createObjectToTest($indexNameFetcher);

        $this->expectException(NoSuchEntityException::class);

        $this->invokeMethod(
            $indexOptionsBuilder,
            'computeIndexName',
            [$indexOptions]
        );
    }

    public function testBuildWithComputedIndexWithTemporaryIndex(): void
    {
        $indexSuffix = '_categories';
        $storeId = 2;
        $isTmp = true;
        $computedIndexName = 'magento2_store2_categories_tmp';

        $indexOptions = $this->createIndexOptionsMock(null, $storeId, $indexSuffix, $isTmp);

        $factory = $this->createIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::STORE_ID => $storeId,
            IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
            IndexOptionsInterface::IS_TMP => $isTmp,
        ]);

        $indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $indexNameFetcher->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willReturn($computedIndexName);

        $indexOptionsBuilder = $this->createObjectToTest($indexNameFetcher, $factory);

        $result = $indexOptionsBuilder->buildWithComputedIndex($indexSuffix, $storeId, $isTmp);

        $this->assertEquals($computedIndexName, $result->getIndexName());
    }

    public function testBuildWithComputedIndexWithDefaultStore(): void
    {
        $indexSuffix = '_pages';
        $storeId = null;
        $isTmp = false;
        $computedIndexName = 'magento2_default_pages';

        $indexOptions = $this->createIndexOptionsMock(null, $storeId, $indexSuffix, $isTmp);

        $factory = $this->createIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::STORE_ID => $storeId,
            IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
            IndexOptionsInterface::IS_TMP => $isTmp,
        ]);

        $indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $indexNameFetcher->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willReturn($computedIndexName);

        $indexOptionsBuilder = $this->createObjectToTest($indexNameFetcher, $factory);

        $result = $indexOptionsBuilder->buildWithComputedIndex($indexSuffix, $storeId, $isTmp);

        $this->assertEquals($computedIndexName, $result->getIndexName());
    }

    public function testBuildWithEnforcedIndexWithOnlyIndexName(): void
    {
        $indexName = 'custom_enforced_index';

        $indexOptions = $this->createIndexOptionsMock($indexName);

        $factory = $this->createIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::INDEX_NAME => $indexName,
            IndexOptionsInterface::STORE_ID => null,
        ]);

        $indexOptionsBuilder = $this->createObjectToTest(indexOptionsInterfaceFactory: $factory);

        $result = $indexOptionsBuilder->buildWithEnforcedIndex($indexName);

        $this->assertEquals($indexName, $result->getIndexName());
    }

    public function testBuildWithEnforcedIndexWithOnlyStoreId(): void
    {
        $indexName = null;
        $storeId = 3;

        $indexOptions = $this->createIndexOptionsMock($indexName, $storeId);

        $factory = $this->createIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::INDEX_NAME => $indexName,
            IndexOptionsInterface::STORE_ID => $storeId,
        ]);

        $indexOptionsBuilder = $this->createObjectToTest(indexOptionsInterfaceFactory: $factory);

        $result = $indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->assertEquals($indexName, $result->getIndexName()); // Should this throw an exception?
    }
}
