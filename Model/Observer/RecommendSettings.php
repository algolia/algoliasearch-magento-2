<?php
declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Model\Observer;

use Algolia\AlgoliaSearch\Api\RecommendManagementInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;

class RecommendSettings implements ObserverInterface
{
    /**
     * @var string
     */
    private $productId = '';

    /**
     * @param ConfigHelper $configHelper
     * @param WriterInterface $configWriter
     * @param ProductRepositoryInterface $productRepository
     * @param RecommendManagementInterface $recommendManagement
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly ConfigHelper                 $configHelper,
        private readonly WriterInterface              $configWriter,
        private readonly ProductRepositoryInterface   $productRepository,
        private readonly RecommendManagementInterface $recommendManagement,
        private readonly SearchCriteriaBuilder        $searchCriteriaBuilder
    ){}

    /**
     * @param Observer $observer
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        foreach ($observer->getData('changed_paths') as $changedPath) {
            // Validate before enable FBT on PDP
            if (
                $changedPath == $this->configHelper::IS_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_ENABLED
                && $this->configHelper->isRecommendFrequentlyBroughtTogetherEnabled()
            ) {
                $this->validateFrequentlyBroughtTogether($changedPath);
            }

            // Validate before enable FBT on cart page
            if (
                $changedPath == $this->configHelper::IS_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_ENABLED_ON_CART_PAGE
                && $this->configHelper->isRecommendFrequentlyBroughtTogetherEnabledOnCartPage()
            ) {
                $this->validateFrequentlyBroughtTogether($changedPath);
            }

            // Validate before enable related products on PDP
            if (
                $changedPath == $this->configHelper::IS_RECOMMEND_RELATED_PRODUCTS_ENABLED
                && $this->configHelper->isRecommendRelatedProductsEnabled()
            ) {
                $this->validateRelatedProducts($changedPath);
            }

            // Validate before enable related products on cart page
            if (
                $changedPath == $this->configHelper::IS_RECOMMEND_RELATED_PRODUCTS_ENABLED_ON_CART_PAGE
                && $this->configHelper->isRecommendRelatedProductsEnabledOnCartPage()
            ) {
                $this->validateRelatedProducts($changedPath);
            }

            // Validate before enable trending items on PDP
            if (
                $changedPath == $this->configHelper::IS_TREND_ITEMS_ENABLED_IN_PDP
                && $this->configHelper->isTrendItemsEnabledInPDP()
            ) {
                $this->validateTrendingItems($changedPath);
            }

            // Validate before enable trending items on cart page
            if (
                $changedPath == $this->configHelper::IS_TREND_ITEMS_ENABLED_IN_SHOPPING_CART
                && $this->configHelper->isTrendItemsEnabledInShoppingCart()
            ) {
                $this->validateTrendingItems($changedPath);
            }

            // Validate before enable looking similar on PDP
            if (
                $changedPath == $this->configHelper::IS_LOOKING_SIMILAR_ENABLED_IN_PDP
                && $this->configHelper->isLookingSimilarEnabledInPDP()
            ) {
                $this->validateLookingSimilar($changedPath);
            }

            // Validate before enable looking similar on cart page
            if (
                $changedPath == $this->configHelper::IS_LOOKING_SIMILAR_ENABLED_IN_SHOPPING_CART
                && $this->configHelper->isLookingSimilarEnabledInShoppingCart()
            ) {
                $this->validateLookingSimilar($changedPath);
            }
        }
    }

    /**
     * @param string $changedPath
     * @return void
     * @throws LocalizedException
     */
    protected function validateFrequentlyBroughtTogether(string $changedPath): void
    {
        try {
            $recommendations = $this->recommendManagement->getBoughtTogetherRecommendation($this->getProductId());
            if (empty($recommendations['renderingContent'])) {
                $this->configWriter->save($changedPath, 0);
                throw new LocalizedException(__(
                    "It appears that there is no trained model available for Frequently Bought Together recommendation for the AppID: %1.",
                    $this->configHelper->getApplicationID()
                ));
            }
        } catch (\Exception $e) {
            $this->configWriter->save($changedPath, 0);
            throw new LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * @param string $changedPath
     * @return void
     * @throws LocalizedException
     */
    protected function validateRelatedProducts(string $changedPath): void
    {
        try {
            $recommendations = $this->recommendManagement->getRelatedProductsRecommendation($this->getProductId());
            if (empty($recommendations['renderingContent'])) {
                $this->configWriter->save($changedPath, 0);
                throw new LocalizedException(__(
                    "It appears that there is no trained model available for Related Products recommendation for the AppID: %1.",
                    $this->configHelper->getApplicationID()
                ));
            }
        } catch (\Exception $e) {
            $this->configWriter->save($changedPath, 0);
            throw new LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * @param string $changedPath
     * @return void
     * @throws LocalizedException
     */
    protected function validateTrendingItems(string $changedPath): void
    {
        try {
            $recommendations = $this->recommendManagement->getTrendingItemsRecommendation();
            // When no recommendations suggested, most likely trained model is missing
            if (empty($recommendations['renderingContent'])) {
                $this->configWriter->save($changedPath, 0);
                throw new LocalizedException(__(
                    "It appears that there is no trained model available for Trending Items recommendation for the AppID: %1.",
                    $this->configHelper->getApplicationID()
                ));
            }
        } catch (\Exception $e) {
            $this->configWriter->save($changedPath, 0);
            throw new LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * @param string $changedPath
     * @return void
     * @throws LocalizedException
     */
    protected function validateLookingSimilar(string $changedPath): void
    {
        try {
            $recommendations = $this->recommendManagement->getLookingSimilarRecommendation($this->getProductId());
            if (empty($recommendations['renderingContent'])) {
                $this->configWriter->save($changedPath, 0);
                throw new LocalizedException(__(
                    "It appears that there is no trained model available for Looking Similar Recommendation for the AppID: %1.",
                    $this->configHelper->getApplicationID()
                ));
            }
        } catch (\Exception $e) {
            $this->configWriter->save($changedPath, 0);
            throw new LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * @return string
     */
    private function getProductId(): string
    {
        if ($this->productId === '') {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('status', 1)
                ->create();
            $result = $this->productRepository->getList($searchCriteria);
            $products = array_reverse($result->getItems());
            $firstProduct = array_pop($products);
            $this->productId = (string)$firstProduct->getId();
        }

        return $this->productId;
    }
}
