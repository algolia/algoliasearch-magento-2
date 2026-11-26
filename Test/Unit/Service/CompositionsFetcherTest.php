<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\CompositionClient;
use Algolia\AlgoliaSearch\Api\CompositionClientProviderInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Service\CompositionsFetcher;
use Algolia\AlgoliaSearch\Service\Index\IndexNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class CompositionsFetcherTest extends TestCase
{
    protected const PRODUCT_INDEX = 'magento_default_products';

    protected function createObjectToTest(
        ?CompositionClientProviderInterface $clientProvider = null,
        ?IndexNameFetcher $indexNameFetcher = null,
    ): CompositionsFetcher {
        return new CompositionsFetcher(
            $clientProvider ?? $this->createStub(CompositionClientProviderInterface::class),
            $indexNameFetcher ?? $this->createStub(IndexNameFetcher::class),
        );
    }

    protected function createClientProviderReturning(array $compositions): CompositionClientProviderInterface
    {
        $client = $this->createStub(CompositionClient::class);
        $client->method('listCompositions')->willReturn($compositions);

        $provider = $this->createStub(CompositionClientProviderInterface::class);
        $provider->method('getClient')->willReturn($client);

        return $provider;
    }

    protected function createIndexNameFetcherStub(string $productIndexName = self::PRODUCT_INDEX): IndexNameFetcher
    {
        $indexNameFetcher = $this->createStub(IndexNameFetcher::class);
        $indexNameFetcher->method('getProductIndexName')->willReturn($productIndexName);

        return $indexNameFetcher;
    }

    protected function buildCompositions(array $objectIds): array
    {
        $items = array_map(
            static fn(string $objectId): array => ['objectID' => $objectId, 'name' => 'Composition ' . $objectId],
            $objectIds
        );

        return ['items' => $items, 'nbHits' => count($items)];
    }

    public function testFetchCompositionsRequestsClientForGivenStore(): void
    {
        $client = $this->createStub(CompositionClient::class);
        $client->method('listCompositions')->willReturn(['items' => [], 'nbHits' => 0]);

        $provider = $this->createMock(CompositionClientProviderInterface::class);
        $provider->expects($this->once())->method('getClient')->with(3)->willReturn($client);

        $this->createObjectToTest($provider)->fetchCompositions(3);
    }

    public function testFetchCompositionsReturnsAllCompositionsWhenStoreIdIsNull(): void
    {
        $compositions = $this->buildCompositions([
            'comp1___magento_default_products',
            'comp2___magento_fr_products',
        ]);

        $fetcher = $this->createObjectToTest(
            $this->createClientProviderReturning($compositions),
            $this->createIndexNameFetcherStub()
        );

        $this->assertSame($compositions, $fetcher->fetchCompositions());
    }

    public function testFetchCompositionsReturnsEmptyResultUntouched(): void
    {
        $compositions = ['items' => [], 'nbHits' => 0];

        $fetcher = $this->createObjectToTest(
            $this->createClientProviderReturning($compositions),
            $this->createIndexNameFetcherStub()
        );

        $this->assertSame($compositions, $fetcher->fetchCompositions(1));
    }

    public function testFetchCompositionsFiltersByStoreProductIndex(): void
    {
        $compositions = $this->buildCompositions([
            'comp1___magento_default_products',
            'comp2___magento_fr_products',
            'comp3___magento_default_products',
        ]);

        $fetcher = $this->createObjectToTest(
            $this->createClientProviderReturning($compositions),
            $this->createIndexNameFetcherStub()
        );

        $result = $fetcher->fetchCompositions(1);

        $this->assertSame(2, $result['nbHits']);
        $this->assertSame(
            ['comp1___magento_default_products', 'comp3___magento_default_products'],
            array_column($result['items'], 'objectID')
        );
    }

    public function testFetchCompositionsReturnsNoItemsWhenNothingMatchesStore(): void
    {
        $compositions = $this->buildCompositions(['comp1___magento_fr_products']);

        $fetcher = $this->createObjectToTest(
            $this->createClientProviderReturning($compositions),
            $this->createIndexNameFetcherStub()
        );

        $result = $fetcher->fetchCompositions(1);

        $this->assertSame(0, $result['nbHits']);
        $this->assertSame([], $result['items']);
    }

    public function testFetchCompositionsPropagatesClientProviderException(): void
    {
        $provider = $this->createStub(CompositionClientProviderInterface::class);
        $provider->method('getClient')->willThrowException(new AlgoliaException('No credentials'));

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('No credentials');

        $this->createObjectToTest($provider)->fetchCompositions(1);
    }

    #[DataProvider('compositionIdProvider')]
    public function testExtractIndexName(string $compositionId, string $expected): void
    {
        $this->assertSame($expected, CompositionsFetcher::extractIndexName($compositionId));
    }

    public static function compositionIdProvider(): array
    {
        return [
            'standard id'              => ['comp1700000000___magento_default_products', 'magento_default_products'],
            'no separator'             => ['comp1700000000', ''],
            'empty string'             => ['', ''],
            'empty index name'         => ['comp1700000000___', ''],
            'extra separator ignored'  => ['comp1___my_index___suffix', 'my_index'],
        ];
    }
}
