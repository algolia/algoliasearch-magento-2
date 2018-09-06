<?php

namespace Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager;

use Magento\Catalog\Model\Product;

class Bundle extends ProductWithChildren
{
    protected function getMinMaxPrices(Product $product, $withTax, $subProducts, $currencyCode)
    {
        $min = PHP_INT_MAX;
        $max = 0;

        /** @var \Magento\Bundle\Model\Product\Price $priceModel */
        $priceModel = $product->getPriceModel();
        list($min, $max) = $priceModel->getTotalPrices($product, null, $withTax, true);

        if ($currencyCode !== $this->baseCurrencyCode) {
            $min = $this->convertPrice($min, $currencyCode);

            if ($min !== $max) {
                $max = $this->convertPrice($max, $currencyCode);
            }
        }

        return [$min, $max];
    }
}
