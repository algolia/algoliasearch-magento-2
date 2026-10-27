<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Weee\Model\Tax as WeeeTax;

class PricingHelper
{
    public function __construct(
        protected ConfigHelper $configHelper,
        protected CatalogHelper $catalogHelper,
        protected PriceCurrencyInterface $priceCurrency,
        protected WeeeTax $weeeTax
    ) {}

    public function convertPrice($amount, $store, $currencyCode): float
    {
        return $this->priceCurrency->convert($amount, $store, $currencyCode);
    }

    public function handleConversion(?float $amount, PricingContextInterface $pricingContext): float|null
    {
        if ($amount === null || !$pricingContext->isCurrencyDifferentFromBase()) {
            return $amount;
        }

        return $this->convertPrice($amount, $pricingContext->getStore(), $pricingContext->getCurrencyCode());
    }

    public function round(float $price):float
    {
        return $this->priceCurrency->round($price);
    }

    public function getTaxPrice($product, $amount, $withTax, $round = true): float
    {
        return (float) $this->catalogHelper->getTaxPrice(
            $product,
            $amount,
            $withTax,
            null,
            null,
            null,
            $product->getStore(),
            null,
            $round
        );
    }

    public function formatPrice($amount, $store, $currencyCode): mixed
    {
        $currency = $this->priceCurrency->getCurrency($store, $currencyCode);
        $options = ['locale' => $this->configHelper->getStoreLocale($store->getId())];
        return $currency->formatPrecision($amount, PriceCurrencyInterface::DEFAULT_PRECISION, $options, false);
    }

    public function getWeeeAmount($product):float
    {
        return $this->weeeTax->getWeeeAmount($product);
    }
}
