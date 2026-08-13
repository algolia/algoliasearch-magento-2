<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Algolia\AlgoliaSearch\Block;

use Magento\Catalog\Model\Product;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Registry\CurrentProduct;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class RecommendProductView extends Template
{
    /** @var Product */
    protected $product = null;

    /** @var CurrentProduct */
    protected $currentProduct;

    /** @var ConfigHelper */
    protected $configHelper;

    public function __construct(
        Context      $context,
        CurrentProduct     $currentProduct,
        ConfigHelper $configHelper,
        array        $data = []
    ) {
        $this->currentProduct = $currentProduct;
        $this->configHelper = $configHelper;
        parent::__construct($context, $data);
    }

    /**
     * Returns a Product
     *
     * @return Product
     */
    public function getProduct()
    {
        if (!$this->product) {
            $this->product = $this->currentProduct->get();
        }

        return $this->product;
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
        ];
    }
}
