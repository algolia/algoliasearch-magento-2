<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\SearchClientProviderInterface;
use Algolia\AlgoliaSearch\Api\SendStrategyInterface;

class DirectSendStrategy implements SendStrategyInterface
{
    public function __construct(
        private SearchClientProviderInterface $clientProvider
    ) {}

    public function isApplicable(int $storeId): bool
    {
        return true;
    }

    public function send(IndexOptionsInterface $indexOptions, array $requests): array
    {
        return $this->clientProvider->getClient($indexOptions->getStoreId())
            ->batch($indexOptions->getIndexName(), ['requests' => $requests]);
    }
}
