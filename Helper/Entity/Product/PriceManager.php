<?php

namespace Algolia\AlgoliaSearch\Helper\Entity\Product;

use Algolia\AlgoliaSearch\Service\Product\Pricing\Bundle as PriceManagerBundle;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Configurable as PriceManagerConfigurable;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Downloadable as PriceManagerDownloadable;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Grouped as PriceManagerGrouped;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Simple as PriceManagerSimple;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Virtual as PriceManagerVirtual;
use Magento\Catalog\Model\Product;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Config as TaxConfig;

class PriceManager
{
    public function __construct(
        protected TaxHelper $taxHelper,
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

        $priceFields = $this->getPriceFields($product);

        // price/price_with_tax => true/false
        foreach ($priceFields as $field => $withTax) {
            $customData[$field] = $this->{$priceManager}->getPriceData($product, $subProducts, $withTax);
        }

        return $customData;
    }

    /**
     * @param Product $product
     * @return array
     */
    protected function getPriceFields(Product $product): array
    {
        $priceDisplayType = $this->taxHelper->getPriceDisplayType($product->getStore());
        if ($priceDisplayType === TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX) {
            return ['price' => false];
        }
        if ($priceDisplayType === TaxConfig::DISPLAY_TYPE_INCLUDING_TAX) {
            return ['price' => true];
        }
        return ['price' => false, 'price_with_tax' => true];
    }
}
