<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Api\RecommendClient;
use Algolia\AlgoliaSearch\Api\RecommendManagementInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Magento\Framework\Exception\NoSuchEntityException;

class RecommendManagement implements RecommendManagementInterface
{
    protected ?RecommendClient $client = null;

    public function __construct(
        protected readonly ConfigHelper          $configHelper,
        protected readonly IndexNameFetcher      $indexNameFetcher
    ){}

    protected function getClient(): RecommendClient
    {
        if ($this->client === null) {
            $this->client = RecommendClient::create(
                $this->configHelper->getApplicationID(),
                $this->configHelper->getAPIKey()
            );
        }

        return $this->client;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getBoughtTogetherRecommendation(string $productId): array
    {
        return $this->getRecommendations($productId, 'bought-together');
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getRelatedProductsRecommendation(string $productId): array
    {
        return $this->getRecommendations($productId, 'related-products');
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getTrendingItemsRecommendation(): array
    {
        return $this->getRecommendations('', 'trending-items');
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getLookingSimilarRecommendation(string $productId): array
    {
        return $this->getRecommendations($productId, 'looking-similar');
    }

    /**
     * @throws NoSuchEntityException
     */
    protected function getRecommendations(string $productId, string $model, float|int $threshold = 50): array
    {
        $request['indexName'] = $this->indexNameFetcher->getIndexName('_products');
        $request['model'] = $model;
        $request['threshold'] = $threshold;
        if (!empty($productId)) {
            $request['objectID'] = $productId;
        }

        $client = $this->getClient();
        $recommendations = $client->getRecommendations(
            [
                'requests' => [
                    $request,
                ],
            ],
        );

        return $recommendations['results'][0] ?? [];
    }
}
