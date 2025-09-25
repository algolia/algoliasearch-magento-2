<?php

namespace Algolia\AlgoliaSearch\Helper;

use DateTime;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\CatalogRule\Model\ResourceModel\Rule;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Weee\Model\Tax as WeeeTax;

class PricingHelper
{
    public function __construct(
        protected ConfigHelper           $configHelper,
        protected CatalogHelper          $catalogHelper,
        protected PriceCurrencyInterface $priceCurrency,
        protected WeeeTax                $weeeTax,
        protected Rule                   $rule
    ) {}

    /**
     * @param $amount
     * @param $store
     * @param $currencyCode
     * @return float
     */
    public function convertPrice($amount, $store, $currencyCode): float
    {
        return $this->priceCurrency->convert($amount, $store, $currencyCode);
    }

    /**
     * @param float $price
     * @return float
     */
    public function round(float $price):float
    {
        return $this->priceCurrency->round($price);
    }

    /**
     * @param $product
     * @param $amount
     * @param $withTax
     * @return float
     */
    public function getTaxPrice($product, $amount, $withTax): float
    {
        return (float) $this->catalogHelper->getTaxPrice(
            $product,
            $amount,
            $withTax,
            null,
            null,
            null,
            $product->getStore(),
            null
        );
    }

    /**
     * @param $amount
     * @param $store
     * @param $currencyCode
     * @return mixed
     */
    public function formatPrice($amount, $store, $currencyCode): mixed
    {
        $currency = $this->priceCurrency->getCurrency($store, $currencyCode);
        $options = ['locale' => $this->configHelper->getStoreLocale($store->getId())];
        return $currency->formatPrecision($amount, PriceCurrencyInterface::DEFAULT_PRECISION, $options, false);
    }

    /**
     * @param $min
     * @param $max
     * @param $store
     * @param $currencyCode
     * @return string
     */
    public function formatDashedPriceFormat($min, $max, $store, $currencyCode): string
    {
        if ($min === $max) {
            return '';
        }
        return $this->formatPrice($min, $store, $currencyCode) . ' - ' . $this->formatPrice($max, $store, $currencyCode);
    }

    /**
     * @param $min
     * @param $max
     * @param $store
     * @param $currencyCode
     * @return mixed|string
     */
    public function formattedConfigPrice($min, $max, $store, $currencyCode): mixed
    {
        if ($min != $max) {
            return $this->formatDashedPriceFormat($min, $max, $store, $currencyCode);
        } else {
            return $this->formatPrice($min, $store, $currencyCode);
        }
    }

    /**
     * @param $groupId
     * @param $product
     * @return float
     */
    public function getRulePrice($groupId, $product): float
    {
        return (float) $this->rule->getRulePrice(
            new DateTime(),
            $product->getStore()->getWebsiteId(),
            $groupId,
            $product->getId()
        );
    }

    /**
     * @param $product
     * @return float
     */
    public function getWeeeAmount($product):float
    {
        return $this->weeeTax->getWeeeAmount($product);
    }
}
