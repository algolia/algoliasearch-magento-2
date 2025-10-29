<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterfaceFactory;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;

class IndexOptionsBuilderTest extends TestCase
{
    private IndexOptionsBuilder|MockObject $indexOptionsBuilder;
    private IndexNameFetcher|MockObject $indexNameFetcher;
    private IndexOptionsInterfaceFactory|MockObject $indexOptionsInterfaceFactory;
    private DiagnosticsLogger|MockObject $logger;

    protected function setUp(): void
    {
        $this->indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $this->indexOptionsInterfaceFactory = $this->createMock(IndexOptionsInterfaceFactory::class);
        $this->logger = $this->createMock(DiagnosticsLogger::class);

        $this->indexOptionsBuilder = new IndexOptionsBuilder(
            $this->indexNameFetcher,
            $this->indexOptionsInterfaceFactory,
            $this->logger
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
            IndexOptionsInterface::IS_TMP => $isTmp
        ]);
    }

    private function configureIndexOptionsFactoryMock(
        IndexOptionsInterface $indexOptions,
        array $expectedData
    ): IndexOptionsInterfaceFactory
    {
        $factory = $this->indexOptionsInterfaceFactory;
        $factory->expects($this->once())
            ->method('create')
            ->with([ 'data' => $expectedData ])
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
        $this->configureIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::STORE_ID => $storeId,
            IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
            IndexOptionsInterface::IS_TMP => $isTmp
        ]);

        $this->indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willReturn($computedIndexName);

        $result = $this->indexOptionsBuilder->buildWithComputedIndex($indexSuffix, $storeId, $isTmp);

        $this->assertEquals($computedIndexName, $result->getIndexName());
    }

    public function testBuildWithComputedIndexWithNullParameters(): void
    {
        $indexOptions = $this->createIndexOptionsMock(null, null, null, false);

        $this->configureIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::STORE_ID => null,
            IndexOptionsInterface::INDEX_SUFFIX => null,
            IndexOptionsInterface::IS_TMP => false
        ]);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Index name could not be computed due to missing suffix.',
                [
                    'storeId' => null,
                    'isTmp' => false
                ]
            );

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Index name could not be computed due to missing suffix.');

        $this->indexOptionsBuilder->buildWithComputedIndex();
    }

    public function testBuildWithComputedIndexWithIndexNameFetcherException(): void
    {
        $indexSuffix = '_products';
        $storeId = 1;
        $isTmp = false;

        $indexOptions = $this->createIndexOptionsMock(null, $storeId, $indexSuffix, $isTmp);

        $this->configureIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::STORE_ID => $storeId,
            IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
            IndexOptionsInterface::IS_TMP => $isTmp
        ]);

        $this->indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willThrowException(new NoSuchEntityException(__('Store not found')));

        $this->expectException(NoSuchEntityException::class);

        $this->indexOptionsBuilder->buildWithComputedIndex($indexSuffix, $storeId, $isTmp);
    }

    public function testBuildWithEnforcedIndexWithAllParameters(): void
    {
        $indexName = 'custom_index_name';
        $storeId = 2;

        $indexOptions = $this->createIndexOptionsMock($indexName, $storeId);

        $this->configureIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::INDEX_NAME => $indexName,
            IndexOptionsInterface::STORE_ID => $storeId
        ]);

        $result = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->assertEquals($indexName, $result->getIndexName());
    }

    public function testBuildWithEnforcedIndexWithNullParameters(): void
    {
        $indexOptions = $this->createIndexOptionsMock(null, null);

        $this->configureIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::INDEX_NAME => null,
            IndexOptionsInterface::STORE_ID => null
        ]);

        $result = $this->indexOptionsBuilder->buildWithEnforcedIndex();

        $this->assertEquals('', $result->getIndexName()); // Should this throw an exception?
    }

    public function testComputeIndexNameWithEnforcedIndexName(): void
    {
        $enforcedIndexName = 'enforced_index_name';
        $indexOptions = $this->createIndexOptionsMock($enforcedIndexName);

        $result = $this->invokeMethod(
            $this->indexOptionsBuilder,
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

        $this->indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willReturn($computedIndexName);

        $result = $this->invokeMethod(
            $this->indexOptionsBuilder,
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

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Index name could not be computed due to missing suffix.',
                [
                    'storeId' => $storeId,
                    'isTmp' => $isTmp
                ]
            );

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Index name could not be computed due to missing suffix.');

        $this->invokeMethod(
            $this->indexOptionsBuilder,
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

        $this->indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willThrowException(new NoSuchEntityException(__('Store not found')));

        $this->expectException(NoSuchEntityException::class);

        $this->invokeMethod(
            $this->indexOptionsBuilder,
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

        $this->configureIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::STORE_ID => $storeId,
            IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
            IndexOptionsInterface::IS_TMP => $isTmp
        ]);

        $this->indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willReturn($computedIndexName);

        $result = $this->indexOptionsBuilder->buildWithComputedIndex($indexSuffix, $storeId, $isTmp);

        $this->assertEquals($computedIndexName, $result->getIndexName());
    }

    public function testBuildWithComputedIndexWithDefaultStore(): void
    {
        $indexSuffix = '_pages';
        $storeId = null;
        $isTmp = false;
        $computedIndexName = 'magento2_default_pages';

        $indexOptions = $this->createIndexOptionsMock(null, $storeId, $indexSuffix, $isTmp);

        $this->configureIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::STORE_ID => $storeId,
            IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
            IndexOptionsInterface::IS_TMP => $isTmp
        ]);

        $this->indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willReturn($computedIndexName);

        $result = $this->indexOptionsBuilder->buildWithComputedIndex($indexSuffix, $storeId, $isTmp);

        $this->assertEquals($computedIndexName, $result->getIndexName());
    }

    public function testBuildWithEnforcedIndexWithOnlyIndexName(): void
    {
        $indexName = 'custom_enforced_index';

        $indexOptions = $this->createIndexOptionsMock($indexName);

        $this->configureIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::INDEX_NAME => $indexName,
            IndexOptionsInterface::STORE_ID => null
        ]);

        $result = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName);

        $this->assertEquals($indexName, $result->getIndexName());
    }

    public function testBuildWithEnforcedIndexWithOnlyStoreId(): void
    {
        $indexName = null;
        $storeId = 3;

        $indexOptions = $this->createIndexOptionsMock($indexName, $storeId);

        $this->configureIndexOptionsFactoryMock($indexOptions, [
            IndexOptionsInterface::INDEX_NAME => $indexName,
            IndexOptionsInterface::STORE_ID => $storeId
        ]);

        $result = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->assertEquals($indexName, $result->getIndexName()); // Should this throw an exception?
    }
}
