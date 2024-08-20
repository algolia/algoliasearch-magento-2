<?php
declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Api\RecommendClient;
use Algolia\AlgoliaSearch\Api\RecommendManagementInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class RecommendManagement implements RecommendManagementInterface
{
    /**
     * @var null|RecommendClient
     */
    private $client = null;

    /**
     * @param AlgoliaHelper $algoliaHelper
     * @param ConfigHelper $configHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly AlgoliaHelper         $algoliaHelper,
        private readonly ConfigHelper          $configHelper,
        private readonly StoreManagerInterface $storeManager
    ){}

    /**
     * @return RecommendClient
     */
    private function getClient(): RecommendClient
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
     * @param string $name
     * @return string
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    private function getFullIndexName(string $name): string
    {
        $prefix = $this->configHelper->getIndexPrefix();
        $storeCode = $this->storeManager->getStore()->getCode();

        foreach ($this->algoliaHelper->listIndexes()['items'] as $index) {
            if ($index['name'] == $prefix . $storeCode . '_' . $name) {
                return $index['name'];
            }
        }

        return '';
    }

    /**
     * @param string $productId
     * @return array
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    public function getBoughtTogetherRecommendation(string $productId): array
    {
        return $this->getRecommendations($productId, 'bought-together', 50);
    }

    /**
     * @param string $productId
     * @return array
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    public function getRelatedProductsRecommendation($productId): array
    {
        return $this->getRecommendations($productId, 'related-products', 50);
    }

    /**
     * @return array
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    public function getTrendingItemsRecommendation(): array
    {
        return $this->getRecommendations('', 'trending-items', 50);
    }

    /**
     * @param string $productId
     * @return array
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    public function getLookingSimilarRecommendation($productId): array
    {
        return $this->getRecommendations($productId, 'bought-together', 50);
    }

    /**
     * @param string $productId
     * @param string $model
     * @param float|int $threshold
     * @return array
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    private function getRecommendations(string $productId, string $model, float|int $threshold = 42): array
    {
        $request['indexName'] = $this->getFullIndexName('products');
        $request['model'] = $model;
        $request['threshold'] = $threshold;
        if (!empty($productId)) {
            $request['objectID'] = $productId;
        }

        $client = $this->getClient();
        $recommendations = $client->getRecommendations(
            [
                'requests' => [
                    $request
                ],
            ],
        );

        return $recommendations['results'][0] ?? [];
    }
}
