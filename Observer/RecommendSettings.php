<?php
declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Observer;

use Algolia\AlgoliaSearch\Api\RecommendManagementInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

class RecommendSettings implements ObserverInterface
{
    const QUANTITY_AND_STOCK_STATUS = 'quantity_and_stock_status';
    const STATUS = 'status';
    const VISIBILITY = 'visibility';

    /**
     * @var string
     */
    protected $productId = '';

    /**
     * @param ConfigHelper $configHelper
     * @param WriterInterface $configWriter
     * @param ProductRepositoryInterface $productRepository
     * @param RecommendManagementInterface $recommendManagement
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        protected readonly ConfigHelper                 $configHelper,
        protected readonly WriterInterface              $configWriter,
        protected readonly ProductRepositoryInterface   $productRepository,
        protected readonly RecommendManagementInterface $recommendManagement,
        protected readonly SearchCriteriaBuilder        $searchCriteriaBuilder
    ){}

    /**
     * @param Observer $observer
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        foreach ($observer->getData('changed_paths') as $changedPath) {
            // Validate before enable FBT on PDP or on cart page
            if ((
                    $changedPath == ConfigHelper::IS_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_ENABLED
                    && $this->configHelper->isRecommendFrequentlyBroughtTogetherEnabled()
                ) || (
                    $changedPath == ConfigHelper::IS_RECOMMEND_FREQUENTLY_BOUGHT_TOGETHER_ENABLED_ON_CART_PAGE
                    && $this->configHelper->isRecommendFrequentlyBroughtTogetherEnabledOnCartPage()
            )) {
                $this->validateFrequentlyBroughtTogether($changedPath);
            }

            // Validate before enable related products on PDP or on cart page
            if ((
                    $changedPath == ConfigHelper::IS_RECOMMEND_RELATED_PRODUCTS_ENABLED
                    && $this->configHelper->isRecommendRelatedProductsEnabled()
                ) || (
                    $changedPath == ConfigHelper::IS_RECOMMEND_RELATED_PRODUCTS_ENABLED_ON_CART_PAGE
                    && $this->configHelper->isRecommendRelatedProductsEnabledOnCartPage()
            )) {
                $this->validateRelatedProducts($changedPath);
            }

            // Validate before enable trending items on PDP or on cart page
            if ((
                    $changedPath == ConfigHelper::IS_TREND_ITEMS_ENABLED_IN_PDP
                    && $this->configHelper->isTrendItemsEnabledInPDP()
                ) || (
                    $changedPath == ConfigHelper::IS_TREND_ITEMS_ENABLED_IN_SHOPPING_CART
                    && $this->configHelper->isTrendItemsEnabledInShoppingCart()
            )) {
                $this->validateTrendingItems($changedPath);
            }

            // Validate before enable looking similar on PDP or on cart page
            if ((
                    $changedPath == ConfigHelper::IS_LOOKING_SIMILAR_ENABLED_IN_PDP
                    && $this->configHelper->isLookingSimilarEnabledInPDP()
                ) || (
                    $changedPath == ConfigHelper::IS_LOOKING_SIMILAR_ENABLED_IN_SHOPPING_CART
                    && $this->configHelper->isLookingSimilarEnabledInShoppingCart()
            )) {
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
        $this->validateRecommendation($changedPath, 'getBoughtTogetherRecommendation', 'Frequently Bought Together');
    }

    /**
     * @param string $changedPath
     * @return void
     * @throws LocalizedException
     */
    protected function validateRelatedProducts(string $changedPath): void
    {
        $this->validateRecommendation($changedPath, 'getRelatedProductsRecommendation', 'Related Products');
    }

    /**
     * @param string $changedPath
     * @return void
     * @throws LocalizedException
     */
    protected function validateTrendingItems(string $changedPath): void
    {
        $this->validateRecommendation($changedPath, 'getTrendingItemsRecommendation', 'Trending Items');
    }

    /**
     * @param string $changedPath
     * @return void
     * @throws LocalizedException
     */
    protected function validateLookingSimilar(string $changedPath): void
    {
        $this->validateRecommendation($changedPath, 'getLookingSimilarRecommendation', 'Looking Similar');
    }

    /**
     * @param string $changedPath - config path to be reverted if validation failed
     * @param string $recommendationMethod - name of method to call to retrieve method from RecommendManagementInterface
     * @param string $modelName - user friendly name to refer to model in error messaging
     * @return void
     * @throws LocalizedException
     */
    protected function validateRecommendation(string $changedPath, string $recommendationMethod, string $modelName): void
    {
        try {
            $recommendations = $this->recommendManagement->$recommendationMethod($this->getProductId());
            if (empty($recommendations['renderingContent'])) {
                throw new LocalizedException(__(
                    "It appears that there is no trained model available for Algolia application ID %1.",
                    $this->configHelper->getApplicationID()
                ));
            }
        } catch (\Exception $e) {
            $this->configWriter->save($changedPath, 0);
            throw new LocalizedException(__(
                "Unable to save %1 Recommend configuration due to the following error: %2",
                    $modelName,
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * @return string
     */
    protected function getProductId(): string
    {
        if ($this->productId === '') {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(self::STATUS, 1)
                ->addFilter(self::QUANTITY_AND_STOCK_STATUS, 1)
                ->addFilter(
                    self::VISIBILITY,
                    [
                        Visibility::VISIBILITY_IN_CATALOG,
                        Visibility::VISIBILITY_IN_SEARCH,
                        Visibility::VISIBILITY_BOTH
                    ],
                    'in')
                ->setPageSize(1)
                ->setCurrentPage(1)
                ->create();
            $result = $this->productRepository->getList($searchCriteria);
            $items = $result->getItems();
            $firstProduct = reset($items);
            if ($firstProduct) {
                $this->productId = (string) $firstProduct->getId();
            } else {
                throw new LocalizedException(__("Unable to locate product to validate Recommend model."));
            }
        }

        return $this->productId;
    }
}
