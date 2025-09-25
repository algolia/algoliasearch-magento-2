<?php

namespace Algolia\AlgoliaSearch\Helper\Entity\Product;

use Algolia\AlgoliaSearch\Service\Product\Pricing\Bundle as PriceManagerBundle;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Configurable as PriceManagerConfigurable;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Downloadable as PriceManagerDownloadable;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Grouped as PriceManagerGrouped;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Simple as PriceManagerSimple;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Virtual as PriceManagerVirtual;
use Magento\Catalog\Model\Product;

class PriceManager
{
    public function __construct(
        protected PriceManagerSimple $priceManagerSimple,
        protected PriceManagerVirtual $priceManagerVirtual,
        protected PriceManagerDownloadable $priceManagerDownloadable,
        protected PriceManagerConfigurable $priceManagerConfigurable,
        protected PriceManagerBundle $priceManagerBundle,
        protected PriceManagerGrouped $priceManagerGrouped
    ) {}

    /**
     * @param $customData
     * @param Product $product
     * @param $subProducts
     * @return mixed
     */
    public function addPriceDataByProductType($customData, Product $product, $subProducts)
    {
        $priceManager = 'priceManager' . ucfirst($product->getTypeId());
        if (!property_exists($this, $priceManager)) {
            $priceManager = 'priceManagerSimple';
        }
        return $this->{$priceManager}->addPriceData($customData, $product, $subProducts);
    }
}
