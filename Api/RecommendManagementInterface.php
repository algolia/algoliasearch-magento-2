<?php
namespace Algolia\AlgoliaSearch\Api;
interface RecommendManagementInterface
{
    /**
     * @param string $productId
     * @return array
     */
    public function getBoughtTogetherRecommendation(string $productId): array;

    /**
     * @param string $productId
     * @return array
     */
    public function getRelatedProductsRecommendation(string $productId): array;

    /**
     * @return array
     */
    public function getTrendingItemsRecommendation(): array;

    /**
     * @param string $productId
     * @return array
     */
    public function getLookingSimilarRecommendation(string $productId): array;
}
