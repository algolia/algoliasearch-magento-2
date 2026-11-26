<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Source;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Model\Source\Compositions;
use Algolia\AlgoliaSearch\Service\CompositionsFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class CompositionsTest extends TestCase
{
    protected const EMPTY_OPTION = ['value' => '', 'label' => '---'];

    protected function createObjectToTest(
        ?CompositionsFetcher $compositionsFetcher = null,
        ?RequestInterface $request = null,
    ): Compositions {
        return new Compositions(
            $compositionsFetcher ?? $this->createStub(CompositionsFetcher::class),
            $request ?? $this->createStub(RequestInterface::class),
        );
    }

    protected function createRequestStub(mixed $storeParam): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn($storeParam);

        return $request;
    }

    protected function createFetcherStub(array $compositions): CompositionsFetcher
    {
        $fetcher = $this->createStub(CompositionsFetcher::class);
        $fetcher->method('fetchCompositions')->willReturn($compositions);

        return $fetcher;
    }

    public function testToOptionArrayReadsStoreParamFromRequest(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getParam')->with('store')->willReturn(null);

        $this->createObjectToTest($this->createFetcherStub(['items' => [], 'nbHits' => 0]), $request)
            ->toOptionArray();
    }

    #[DataProvider('storeParamProvider')]
    public function testToOptionArrayPassesStoreIdToFetcher(mixed $storeParam, ?int $expectedStoreId): void
    {
        $fetcher = $this->createMock(CompositionsFetcher::class);
        $fetcher->expects($this->once())
            ->method('fetchCompositions')
            ->with($expectedStoreId)
            ->willReturn(['items' => [], 'nbHits' => 0]);

        $this->createObjectToTest($fetcher, $this->createRequestStub($storeParam))->toOptionArray();
    }

    public static function storeParamProvider(): array
    {
        return [
            'no store param'    => [null, null],
            'string store id'   => ['2', 2],
            'integer store id'  => [3, 3],
            'default store "0"' => ['0', 0],
        ];
    }

    public function testToOptionArrayReturnsOnlyEmptyOptionWhenNoCompositions(): void
    {
        $source = $this->createObjectToTest($this->createFetcherStub(['items' => [], 'nbHits' => 0]));

        $this->assertSame([self::EMPTY_OPTION], $source->toOptionArray());
    }

    public function testToOptionArrayReturnsOnlyEmptyOptionWhenFetcherThrows(): void
    {
        $fetcher = $this->createStub(CompositionsFetcher::class);
        $fetcher->method('fetchCompositions')->willThrowException(new AlgoliaException('No credentials'));

        $source = $this->createObjectToTest($fetcher);

        $this->assertSame([self::EMPTY_OPTION], $source->toOptionArray());
    }

    public function testToOptionArrayFormatsFetchedCompositions(): void
    {
        $compositions = [
            'nbHits' => 2,
            'items'  => [
                ['objectID' => 'comp1___magento_default_products', 'name' => 'Summer'],
                ['objectID' => 'comp2___magento_fr_products', 'name' => 'Winter'],
            ],
        ];

        $options = $this->createObjectToTest($this->createFetcherStub($compositions))->toOptionArray();

        $this->assertCount(3, $options);
        $this->assertSame(self::EMPTY_OPTION, $options[0]);
        $this->assertSame('comp1___magento_default_products', $options[1]['value']);
        $this->assertSame('Summer (Main index: magento_default_products)', (string) $options[1]['label']);
        $this->assertSame('comp2___magento_fr_products', $options[2]['value']);
        $this->assertSame('Winter (Main index: magento_fr_products)', (string) $options[2]['label']);
    }

    public function testToOptionArrayKeepsEmptyOptionFirst(): void
    {
        $compositions = [
            'nbHits' => 1,
            'items'  => [['objectID' => 'comp1___magento_default_products', 'name' => 'Summer']],
        ];

        $options = $this->createObjectToTest($this->createFetcherStub($compositions))->toOptionArray();

        $this->assertSame(self::EMPTY_OPTION, $options[0]);
    }
}
