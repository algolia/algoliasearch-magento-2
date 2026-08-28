<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterfaceFactory;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\Index\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Product\SortingTransformer;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class IndexOptionsBuilderTest extends TestCase
{
    protected function createObjectToTest(
        ?SortingTransformer $sortingTransformer = null,
        ?HttpContext $httpContext = null,
        ?IndexNameFetcher $indexNameFetcher = null,
        ?IndexOptionsInterfaceFactory $indexOptionsFactory = null,
    ): IndexOptionsBuilder {
        return new IndexOptionsBuilder(
            $sortingTransformer ?? $this->createStub(SortingTransformer::class),
            $httpContext ?? $this->createStub(HttpContext::class),
            $indexNameFetcher ?? $this->createStub(IndexNameFetcher::class),
            $indexOptionsFactory ?? $this->createStub(IndexOptionsInterfaceFactory::class),
            $this->createStub(DiagnosticsLogger::class),
        );
    }

    public function testBuildEntityIndexOptionsWithDefaultTmpValue(): void
    {
        $storeId = 1;
        $expectedIndexName = 'store_1_products';

        $indexOptions = $this->createMock(IndexOptionsInterface::class);

        $indexOptionsFactory = $this->createMock(IndexOptionsInterfaceFactory::class);
        $indexOptionsFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::STORE_ID => $storeId,
                    IndexOptionsInterface::INDEX_SUFFIX => ProductHelper::INDEX_NAME_SUFFIX,
                    IndexOptionsInterface::IS_TMP => false,
                ],
            ])
            ->willReturn($indexOptions);

        $indexOptions
            ->expects($this->once())
            ->method('getIndexName')
            ->willReturn(null);

        $indexOptions
            ->expects($this->any())
            ->method('getIndexSuffix')
            ->willReturn(ProductHelper::INDEX_NAME_SUFFIX);

        $indexOptions
            ->expects($this->any())
            ->method('getStoreId')
            ->willReturn($storeId);

        $indexOptions
            ->expects($this->any())
            ->method('isTemporaryIndex')
            ->willReturn(false);

        $indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with(ProductHelper::INDEX_NAME_SUFFIX, $storeId, false)
            ->willReturn($expectedIndexName);

        $indexOptions
            ->expects($this->once())
            ->method('setIndexName')
            ->with($expectedIndexName);

        $indexOptionsBuilder = $this->createObjectToTest(indexNameFetcher: $indexNameFetcher, indexOptionsFactory: $indexOptionsFactory);

        $result = $indexOptionsBuilder->buildEntityIndexOptions($storeId);

        $this->assertSame($indexOptions, $result);
    }

    public function testBuildEntityIndexOptionsWithTmpTrue(): void
    {
        $storeId = 2;
        $isTmp = true;
        $expectedIndexName = 'store_2_products_tmp';

        $indexOptions = $this->createMock(IndexOptionsInterface::class);

        $indexOptionsFactory = $this->createMock(IndexOptionsInterfaceFactory::class);
        $indexOptionsFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::STORE_ID => $storeId,
                    IndexOptionsInterface::INDEX_SUFFIX => ProductHelper::INDEX_NAME_SUFFIX,
                    IndexOptionsInterface::IS_TMP => $isTmp,
                ],
            ])
            ->willReturn($indexOptions);

        $indexOptions
            ->expects($this->once())
            ->method('getIndexName')
            ->willReturn(null);

        $indexOptions
            ->expects($this->any())
            ->method('getIndexSuffix')
            ->willReturn(ProductHelper::INDEX_NAME_SUFFIX);

        $indexOptions
            ->expects($this->any())
            ->method('getStoreId')
            ->willReturn($storeId);

        $indexOptions
            ->expects($this->any())
            ->method('isTemporaryIndex')
            ->willReturn($isTmp);

        $indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with(ProductHelper::INDEX_NAME_SUFFIX, $storeId, $isTmp)
            ->willReturn($expectedIndexName);

        $indexOptions
            ->expects($this->once())
            ->method('setIndexName')
            ->with($expectedIndexName);

        $indexOptionsBuilder = $this->createObjectToTest(indexNameFetcher: $indexNameFetcher, indexOptionsFactory: $indexOptionsFactory);

        $result = $indexOptionsBuilder->buildEntityIndexOptions($storeId, $isTmp);

        $this->assertSame($indexOptions, $result);
    }

    public function testBuildEntityIndexOptionsThrowsNoSuchEntityException(): void
    {
        $storeId = 999;
        $exception = new NoSuchEntityException(__('Store not found'));

        $indexOptions = $this->createMock(IndexOptionsInterface::class);

        $indexOptionsFactory = $this->createMock(IndexOptionsInterfaceFactory::class);
        $indexOptionsFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::STORE_ID => $storeId,
                    IndexOptionsInterface::INDEX_SUFFIX => ProductHelper::INDEX_NAME_SUFFIX,
                    IndexOptionsInterface::IS_TMP => false,
                ],
            ])
            ->willReturn($indexOptions);

        $indexOptions
            ->expects($this->once())
            ->method('getIndexName')
            ->willReturn(null);

        $indexOptions
            ->expects($this->any())
            ->method('getIndexSuffix')
            ->willReturn(ProductHelper::INDEX_NAME_SUFFIX);

        $indexOptions
            ->expects($this->any())
            ->method('getStoreId')
            ->willReturn($storeId);

        $indexOptions
            ->expects($this->any())
            ->method('isTemporaryIndex')
            ->willReturn(false);

        $indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with(ProductHelper::INDEX_NAME_SUFFIX, $storeId, false)
            ->willThrowException($exception);

        $indexOptionsBuilder = $this->createObjectToTest(indexNameFetcher: $indexNameFetcher, indexOptionsFactory: $indexOptionsFactory);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Store not found');

        $indexOptionsBuilder->buildEntityIndexOptions($storeId);
    }

    public function testBuildReplicaIndexOptionsWithValidReplica(): void
    {
        $storeId = 1;
        $sortField = 'price';
        $sortDirection = 'asc';
        $customerGroupId = 0;
        $replicaIndexName = 'store_1_products_price_asc';

        $availableSorts = [
            [
                ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price',
                ReplicaManagerInterface::SORT_KEY_DIRECTION => 'asc',
                ReplicaManagerInterface::SORT_KEY_INDEX_NAME => $replicaIndexName,
            ],
            [
                ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'name',
                ReplicaManagerInterface::SORT_KEY_DIRECTION => 'desc',
                ReplicaManagerInterface::SORT_KEY_INDEX_NAME => 'store_1_products_name_desc',
            ],
        ];

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext
            ->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $sortingTransformer = $this->createMock(SortingTransformer::class);
        $sortingTransformer
            ->expects($this->once())
            ->method('getSortingIndices')
            ->with($storeId, $customerGroupId)
            ->willReturn($availableSorts);

        $indexOptions = $this->createStub(IndexOptionsInterface::class);

        $indexOptionsFactory = $this->createMock(IndexOptionsInterfaceFactory::class);
        $indexOptionsFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::INDEX_NAME => $replicaIndexName,
                    IndexOptionsInterface::STORE_ID => $storeId,
                ],
            ])
            ->willReturn($indexOptions);

        $indexOptionsBuilder = $this->createObjectToTest($sortingTransformer, $httpContext, indexOptionsFactory: $indexOptionsFactory);

        $result = $indexOptionsBuilder->buildReplicaIndexOptions($storeId, $sortField, $sortDirection);

        $this->assertSame($indexOptions, $result);
    }

    public function testBuildReplicaIndexOptionsWithCaseInsensitiveMatch(): void
    {
        $storeId = 1;
        $sortField = 'PRICE';
        $sortDirection = 'ASC';
        $customerGroupId = 1;
        $replicaIndexName = 'store_1_products_price_asc_group_1';

        $availableSorts = [
            [
                ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price',
                ReplicaManagerInterface::SORT_KEY_DIRECTION => 'asc',
                ReplicaManagerInterface::SORT_KEY_INDEX_NAME => $replicaIndexName,
            ],
        ];

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext
            ->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $sortingTransformer = $this->createMock(SortingTransformer::class);
        $sortingTransformer
            ->expects($this->once())
            ->method('getSortingIndices')
            ->with($storeId, $customerGroupId)
            ->willReturn($availableSorts);

        $indexOptions = $this->createStub(IndexOptionsInterface::class);

        $indexOptionsFactory = $this->createMock(IndexOptionsInterfaceFactory::class);
        $indexOptionsFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::INDEX_NAME => $replicaIndexName,
                    IndexOptionsInterface::STORE_ID => $storeId,
                ],
            ])
            ->willReturn($indexOptions);

        $indexOptionsBuilder = $this->createObjectToTest($sortingTransformer, $httpContext, indexOptionsFactory: $indexOptionsFactory);

        $result = $indexOptionsBuilder->buildReplicaIndexOptions($storeId, $sortField, $sortDirection);

        $this->assertSame($indexOptions, $result);
    }

    public function testBuildReplicaIndexOptionsFallsBackToEntityIndexWhenNoReplicaFound(): void
    {
        $storeId = 1;
        $sortField = 'nonexistent';
        $sortDirection = 'asc';
        $customerGroupId = 0;
        $entityIndexName = 'store_1_products';

        $availableSorts = [
            [
                ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price',
                ReplicaManagerInterface::SORT_KEY_DIRECTION => 'asc',
                ReplicaManagerInterface::SORT_KEY_INDEX_NAME => 'store_1_products_price_asc',
            ],
        ];

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext
            ->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $sortingTransformer = $this->createMock(SortingTransformer::class);
        $sortingTransformer
            ->expects($this->once())
            ->method('getSortingIndices')
            ->with($storeId, $customerGroupId)
            ->willReturn($availableSorts);

        $indexOptions = $this->createMock(IndexOptionsInterface::class);

        $indexOptionsFactory = $this->createMock(IndexOptionsInterfaceFactory::class);
        $indexOptionsFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::STORE_ID => $storeId,
                    IndexOptionsInterface::INDEX_SUFFIX => ProductHelper::INDEX_NAME_SUFFIX,
                    IndexOptionsInterface::IS_TMP => false,
                ],
            ])
            ->willReturn($indexOptions);

        $indexOptions
            ->expects($this->once())
            ->method('getIndexName')
            ->willReturn(null);

        $indexOptions
            ->expects($this->any())
            ->method('getIndexSuffix')
            ->willReturn(ProductHelper::INDEX_NAME_SUFFIX);

        $indexOptions
            ->expects($this->any())
            ->method('getStoreId')
            ->willReturn($storeId);

        $indexOptions
            ->expects($this->any())
            ->method('isTemporaryIndex')
            ->willReturn(false);

        $indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with(ProductHelper::INDEX_NAME_SUFFIX, $storeId, false)
            ->willReturn($entityIndexName);

        $indexOptions
            ->expects($this->once())
            ->method('setIndexName')
            ->with($entityIndexName);

        $indexOptionsBuilder = $this->createObjectToTest($sortingTransformer, $httpContext, $indexNameFetcher, $indexOptionsFactory);

        $result = $indexOptionsBuilder->buildReplicaIndexOptions($storeId, $sortField, $sortDirection);

        $this->assertSame($indexOptions, $result);
    }

    public function testBuildReplicaIndexOptionsFallsBackToEntityIndexWhenEmptySortsArray(): void
    {
        $storeId = 1;
        $sortField = 'price';
        $sortDirection = 'asc';
        $customerGroupId = 0;
        $entityIndexName = 'store_1_products';

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext
            ->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $sortingTransformer = $this->createMock(SortingTransformer::class);
        $sortingTransformer
            ->expects($this->once())
            ->method('getSortingIndices')
            ->with($storeId, $customerGroupId)
            ->willReturn([]);

        $indexOptions = $this->createMock(IndexOptionsInterface::class);

        $indexOptionsFactory = $this->createMock(IndexOptionsInterfaceFactory::class);
        $indexOptionsFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'data' => [
                    IndexOptionsInterface::STORE_ID => $storeId,
                    IndexOptionsInterface::INDEX_SUFFIX => ProductHelper::INDEX_NAME_SUFFIX,
                    IndexOptionsInterface::IS_TMP => false,
                ],
            ])
            ->willReturn($indexOptions);

        $indexOptions
            ->expects($this->once())
            ->method('getIndexName')
            ->willReturn(null);

        $indexOptions
            ->expects($this->any())
            ->method('getIndexSuffix')
            ->willReturn(ProductHelper::INDEX_NAME_SUFFIX);

        $indexOptions
            ->expects($this->any())
            ->method('getStoreId')
            ->willReturn($storeId);

        $indexOptions
            ->expects($this->any())
            ->method('isTemporaryIndex')
            ->willReturn(false);

        $indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $indexNameFetcher
            ->expects($this->once())
            ->method('getIndexName')
            ->with(ProductHelper::INDEX_NAME_SUFFIX, $storeId, false)
            ->willReturn($entityIndexName);

        $indexOptions
            ->expects($this->once())
            ->method('setIndexName')
            ->with($entityIndexName);

        $indexOptionsBuilder = $this->createObjectToTest($sortingTransformer, $httpContext, $indexNameFetcher, $indexOptionsFactory);

        $result = $indexOptionsBuilder->buildReplicaIndexOptions($storeId, $sortField, $sortDirection);

        $this->assertSame($indexOptions, $result);
    }

    public function testBuildReplicaIndexOptionsThrowsLocalizedException(): void
    {
        $storeId = 1;
        $sortField = 'price';
        $sortDirection = 'asc';
        $customerGroupId = 0;
        $exception = new LocalizedException(__('Configuration error'));

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext
            ->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $sortingTransformer = $this->createMock(SortingTransformer::class);
        $sortingTransformer
            ->expects($this->once())
            ->method('getSortingIndices')
            ->with($storeId, $customerGroupId)
            ->willThrowException($exception);

        $indexOptionsBuilder = $this->createObjectToTest($sortingTransformer, $httpContext);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Configuration error');

        $indexOptionsBuilder->buildReplicaIndexOptions($storeId, $sortField, $sortDirection);
    }

    public function testGetReplicaIndexNameReturnsIndexName(): void
    {
        $storeId = 1;
        $sortField = 'price';
        $sortDirection = 'desc';
        $customerGroupId = 2;
        $expectedIndexName = 'store_1_products_price_desc';

        $availableSorts = [
            [
                ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'name',
                ReplicaManagerInterface::SORT_KEY_DIRECTION => 'asc',
                ReplicaManagerInterface::SORT_KEY_INDEX_NAME => 'store_1_products_name_asc',
            ],
            [
                ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price',
                ReplicaManagerInterface::SORT_KEY_DIRECTION => 'desc',
                ReplicaManagerInterface::SORT_KEY_INDEX_NAME => $expectedIndexName,
            ],
        ];

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext
            ->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $sortingTransformer = $this->createMock(SortingTransformer::class);
        $sortingTransformer
            ->expects($this->once())
            ->method('getSortingIndices')
            ->with($storeId, $customerGroupId)
            ->willReturn($availableSorts);

        $indexOptionsBuilder = $this->createObjectToTest($sortingTransformer, $httpContext);

        $result = $indexOptionsBuilder->getReplicaIndexName($storeId, $sortField, $sortDirection);

        $this->assertSame($expectedIndexName, $result);
    }

    public function testGetReplicaIndexNameReturnsNullWhenNotFound(): void
    {
        $storeId = 1;
        $sortField = 'rating';
        $sortDirection = 'asc';
        $customerGroupId = 0;

        $availableSorts = [
            [
                ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price',
                ReplicaManagerInterface::SORT_KEY_DIRECTION => 'asc',
                ReplicaManagerInterface::SORT_KEY_INDEX_NAME => 'store_1_products_price_asc',
            ],
        ];

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext
            ->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $sortingTransformer = $this->createMock(SortingTransformer::class);
        $sortingTransformer
            ->expects($this->once())
            ->method('getSortingIndices')
            ->with($storeId, $customerGroupId)
            ->willReturn($availableSorts);

        $indexOptionsBuilder = $this->createObjectToTest($sortingTransformer, $httpContext);

        $result = $indexOptionsBuilder->getReplicaIndexName($storeId, $sortField, $sortDirection);

        $this->assertNull($result);
    }

    public function testGetReplicaIndexNameMatchesOnlyWhenBothFieldAndDirectionMatch(): void
    {
        $storeId = 1;
        $sortField = 'price';
        $sortDirection = 'desc';
        $customerGroupId = 0;

        $availableSorts = [
            [
                ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price',
                ReplicaManagerInterface::SORT_KEY_DIRECTION => 'asc',
                ReplicaManagerInterface::SORT_KEY_INDEX_NAME => 'store_1_products_price_asc',
            ],
        ];

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext
            ->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($customerGroupId);

        $sortingTransformer = $this->createMock(SortingTransformer::class);
        $sortingTransformer
            ->expects($this->once())
            ->method('getSortingIndices')
            ->with($storeId, $customerGroupId)
            ->willReturn($availableSorts);

        $indexOptionsBuilder = $this->createObjectToTest($sortingTransformer, $httpContext);

        $result = $indexOptionsBuilder->getReplicaIndexName($storeId, $sortField, $sortDirection);

        $this->assertNull($result);
    }

    public function testGetCustomerGroupIdReturnsGroupId(): void
    {
        $expectedGroupId = 3;

        $httpContext = $this->createMock(HttpContext::class);
        $httpContext
            ->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn($expectedGroupId);

        $indexOptionsBuilder = $this->createObjectToTest(httpContext: $httpContext);

        $result = $this->invokeMethod($indexOptionsBuilder, 'getCustomerGroupId');

        $this->assertSame($expectedGroupId, $result);
    }

    public function testGetCustomerGroupIdReturnsNull(): void
    {
        $httpContext = $this->createMock(HttpContext::class);
        $httpContext
            ->expects($this->once())
            ->method('getValue')
            ->with(CustomerContext::CONTEXT_GROUP)
            ->willReturn(null);

        $indexOptionsBuilder = $this->createObjectToTest(httpContext: $httpContext);

        $result = $this->invokeMethod($indexOptionsBuilder, 'getCustomerGroupId');

        $this->assertNull($result);
    }
}
