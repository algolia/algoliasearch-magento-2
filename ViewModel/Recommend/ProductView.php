<?php

namespace Algolia\AlgoliaSearch\ViewModel\Recommend;

use Magento\Catalog\Model\Product;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Registry\CurrentProduct;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class ProductView implements ArgumentInterface
{
    /**
     * @var Product
     */
    protected $product = null;

    /**
     * @var CurrentProduct
     */
    protected $currentProduct;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @param CurrentProduct $currentProduct
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        CurrentProduct $currentProduct,
        ConfigHelper $configHelper
    ) {
        $this->currentProduct = $currentProduct;
        $this->configHelper = $configHelper;
    }
    /**
     * Returns a Product
     *
     * @return Product
     */
    public function getProduct()
    {
        if (!$this->product) {
            $this->product = $this->getCurrentProduct();
        }
        return $this->product;
    }

    /**
     * Get product
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrentProduct()
    {
        return $this->currentProduct->get();
    }

    /**
     * @return array
     */
    public function getAlgoliaRecommendConfiguration()
    {
        return [
            'enabledFBT' => $this->configHelper->isRecommendFrequentlyBroughtTogetherEnabled(),
            'enabledRelated' => $this->configHelper->isRecommendRelatedProductsEnabled(),
            'isTrendItemsEnabledInPDP' => $this->configHelper->isTrendItemsEnabledInPDP(),
            'isLookingSimilarEnabledInPDP' => $this->configHelper->isLookingSimilarEnabledInPDP()
        ];
    }
}
