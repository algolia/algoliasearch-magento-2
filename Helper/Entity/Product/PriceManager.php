<?php

namespace Algolia\AlgoliaSearch\Helper\Entity\Product;

use Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager\Bundle as PriceManagerBundle;
use Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager\Configurable as PriceManagerConfigurable;
use Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager\Downloadable as PriceManagerDownloadable;
use Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager\Grouped as PriceManagerGrouped;
use Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager\Simple as PriceManagerSimple;
use Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager\Virtual as PriceManagerVirtual;
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

    public function addPriceDataByProductType($customData, Product $product, $subProducts)
    {
        $priceManager = 'priceManager' . ucfirst($product->getTypeId());
        if (!property_exists($this, $priceManager)) {
            $priceManager = 'priceManagerSimple';
        }

        return $this->{$priceManager}->addPriceData($customData, $product, $subProducts);
    }
}
