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

class IndexOptionsBuilderTest extends TestCase
{
    private IndexOptionsBuilder $indexOptionsBuilder;
    private IndexNameFetcher $indexNameFetcher;
    private IndexOptionsInterfaceFactory $indexOptionsInterfaceFactory;
    private DiagnosticsLogger $logger;

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

    private function createIndexOptionsMock(
        ?string $indexName = null,
        ?int $storeId = null,
        ?string $indexSuffix = null,
        bool $isTmp = false
    ): IndexOptionsInterface {
        $mock = $this->createMock(IndexOptionsInterface::class);
        
        $mock->method('getIndexName')->willReturn($indexName);
        $mock->method('getStoreId')->willReturn($storeId);
        $mock->method('getIndexSuffix')->willReturn($indexSuffix);
        $mock->method('isTemporaryIndex')->willReturn($isTmp);
        
        return $mock;
    }

    public function testBuildWithComputedIndexWithAllParameters(): void
    {
        $indexSuffix = '_products';
        $storeId = 1;
        $isTmp = true;
        $computedIndexName = 'magento2_default_products_tmp';

        $indexOptions = $this->createIndexOptionsMock(null, $storeId, $indexSuffix, $isTmp);

        $this->indexOptionsInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::STORE_ID => $storeId,
                    IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
                    IndexOptionsInterface::IS_TMP => $isTmp
                ]
            ])
            ->willReturn($indexOptions);

        $indexOptions
            ->expects($this->once())
            ->method('setIndexName')
            ->with($computedIndexName);

        $this->indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willReturn($computedIndexName);

        $result = $this->indexOptionsBuilder->buildWithComputedIndex($indexSuffix, $storeId, $isTmp);

        $this->assertSame($indexOptions, $result);
    }

    public function testBuildWithComputedIndexWithNullParameters(): void
    {
        $indexOptions = $this->createIndexOptionsMock(null, null, null, false);

        $this->indexOptionsInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::STORE_ID => null,
                    IndexOptionsInterface::INDEX_SUFFIX => null,
                    IndexOptionsInterface::IS_TMP => false
                ]
            ])
            ->willReturn($indexOptions);

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

        $this->indexOptionsInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($indexOptions);

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

        $this->indexOptionsInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::INDEX_NAME => $indexName,
                    IndexOptionsInterface::STORE_ID => $storeId
                ]
            ])
            ->willReturn($indexOptions);

        $result = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->assertSame($indexOptions, $result);
    }

    public function testBuildWithEnforcedIndexWithNullParameters(): void
    {
        $indexOptions = $this->createIndexOptionsMock(null, null);

        $this->indexOptionsInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::INDEX_NAME => null,
                    IndexOptionsInterface::STORE_ID => null
                ]
            ])
            ->willReturn($indexOptions);

        $result = $this->indexOptionsBuilder->buildWithEnforcedIndex();

        $this->assertSame($indexOptions, $result);
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

        $this->indexOptionsInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::STORE_ID => $storeId,
                    IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
                    IndexOptionsInterface::IS_TMP => $isTmp
                ]
            ])
            ->willReturn($indexOptions);

        $indexOptions
            ->expects($this->once())
            ->method('setIndexName')
            ->with($computedIndexName);

        $this->indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willReturn($computedIndexName);

        $result = $this->indexOptionsBuilder->buildWithComputedIndex($indexSuffix, $storeId, $isTmp);

        $this->assertSame($indexOptions, $result);
    }

    public function testBuildWithComputedIndexWithDefaultStore(): void
    {
        $indexSuffix = '_pages';
        $storeId = null;
        $isTmp = false;
        $computedIndexName = 'magento2_default_pages';

        $indexOptions = $this->createIndexOptionsMock(null, $storeId, $indexSuffix, $isTmp);

        $this->indexOptionsInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::STORE_ID => $storeId,
                    IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
                    IndexOptionsInterface::IS_TMP => $isTmp
                ]
            ])
            ->willReturn($indexOptions);

        $indexOptions
            ->expects($this->once())
            ->method('setIndexName')
            ->with($computedIndexName);

        $this->indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with($indexSuffix, $storeId, $isTmp)
            ->willReturn($computedIndexName);

        $result = $this->indexOptionsBuilder->buildWithComputedIndex($indexSuffix, $storeId, $isTmp);

        $this->assertSame($indexOptions, $result);
    }

    public function testBuildWithEnforcedIndexWithOnlyIndexName(): void
    {
        $indexName = 'custom_enforced_index';
        $storeId = null;

        $indexOptions = $this->createIndexOptionsMock($indexName, $storeId);

        $this->indexOptionsInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::INDEX_NAME => $indexName,
                    IndexOptionsInterface::STORE_ID => $storeId
                ]
            ])
            ->willReturn($indexOptions);

        $result = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->assertSame($indexOptions, $result);
    }

    public function testBuildWithEnforcedIndexWithOnlyStoreId(): void
    {
        $indexName = null;
        $storeId = 3;

        $indexOptions = $this->createIndexOptionsMock($indexName, $storeId);

        $this->indexOptionsInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::INDEX_NAME => $indexName,
                    IndexOptionsInterface::STORE_ID => $storeId
                ]
            ])
            ->willReturn($indexOptions);

        $result = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->assertSame($indexOptions, $result);
    }
}
