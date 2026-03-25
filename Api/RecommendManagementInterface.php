<?php

namespace Algolia\AlgoliaSearch\Api;

interface RecommendManagementInterface
{
    public function getBoughtTogetherRecommendation(string $productId): array;

    public function getRelatedProductsRecommendation(string $productId): array;

    public function getTrendingItemsRecommendation(): array;

    public function getLookingSimilarRecommendation(string $productId): array;
}
