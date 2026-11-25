<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\CompositionClientProviderInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Service\Index\IndexNameFetcher;

class CompositionsFetcher
{
    // Default separator for the composition ID format : comp<TIMESTAMP>___<INDEX_NAME>
    public const string COMPOSITION_ID_SEPARATOR = '___';

    public function __construct(
        protected CompositionClientProviderInterface $compositionClientProvider,
        protected IndexNameFetcher $indexNameFetcher
    ) {}

    /**
     * @throws AlgoliaException
     */
    public function fetchCompositions(?int $storeId = null): array
    {
        $client = $this->compositionClientProvider->getClient($storeId);

        $compositions = $client->listCompositions();
        $this->filterByStoreId($compositions, $storeId);

        return $compositions;
    }

    protected function filterByStoreId(array &$compositions, ?int $storeId = null): void
    {
        if ($storeId === null || $compositions['nbHits'] === 0) {
            return;
        }

        $productIndexName = $this->indexNameFetcher->getProductIndexName($storeId);

        foreach ($compositions['items'] as $key => $composition) {
            $compositionIndexName = self::extractIndexName($composition['objectID']);
            if ($compositionIndexName !== $productIndexName) {
                unset($compositions['items'][$key]);
            }
        }

        $compositions['nbHits'] = count($compositions['items']);
    }

    public static function extractIndexName(string $compositionId): string
    {
        $explodedCompositionId = explode(self::COMPOSITION_ID_SEPARATOR, $compositionId);

        return $explodedCompositionId[1] ?? '';
    }
}
