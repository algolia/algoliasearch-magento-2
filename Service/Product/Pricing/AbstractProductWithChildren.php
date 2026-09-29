<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;

abstract class AbstractProductWithChildren extends AbstractProduct
{
    public const PRICE_NOT_SET = -1;

    protected function addAdditionalData($product, $withTax, $subProducts, $currencyCode): void
    {
        [$min, $max, $minOriginal, $maxOriginal] =
            $this->getMinMaxPrices($product, $withTax, $subProducts, $currencyCode);

        if ($min !== $max) {
            $this->handleNonEqualMinMaxPrices($min, $max);
        }

        $this->priceData->setOriginalPrice($maxOriginal);
        if ($this->priceData->getPrice() === 0.00) {
            $this->priceData->setPrice($min);
        }
        if ($this->areCustomersGroupsEnabled) {
            $this->setFinalGroupPrices($currencyCode, $min, $product, $subProducts, $withTax);
        }
    }

    protected function getMinMaxPrices(Product $product, $withTax, $subProducts, $currencyCode): array
    {
        $min      = PHP_INT_MAX;
        $max      = 0;
        $original = $min;
        $originalMax = $max;
        if (count($subProducts) > 0) {
            /** @var Product $subProduct */
            foreach ($subProducts as $subProduct) {
                $specialPrice = $this->getSpecialPrice($subProduct, $currencyCode, $withTax, $subProducts);
                $tierPrice = $this->getTierPrice($subProduct, $currencyCode, $withTax);
                if (!empty($tierPrice[0]) && $specialPrice[0] > $tierPrice[0]){
                    $minPrice = $tierPrice[0];
                } else {
                    $minPrice = $specialPrice[0];
                }

                $finalPrice = $subProduct->getFinalPrice();
                $basePrice  = $subProduct->getPrice();

                if ($currencyCode !== $this->baseCurrencyCode) {
                    $finalPrice = $this->pricingHelper->convertPrice($finalPrice, $this->store, $currencyCode);
                    $basePrice  = $this->pricingHelper->convertPrice($basePrice, $this->store, $currencyCode);
                }

                $price = $minPrice ?? $this->pricingHelper->getTaxPrice($product, $finalPrice, $withTax);
                $basePrice = $this->pricingHelper->getTaxPrice($product, $basePrice, $withTax);

                if ($this->configHelper->isFptEnabled($subProduct->getStoreId())) {
                    $basePrice += $this->pricingHelper->getWeeeAmount($subProduct);
                }

                $min = min($min, $price);
                $original = min($original, $basePrice);
                $max = max($max, $price);
                $originalMax = max($originalMax, $basePrice);
            }
        } else {
            $originalMax = $original = $min = $max;
        }

        return [$min, $max, $original, $originalMax];
    }

    protected function handleNonEqualMinMaxPrices($min, $max): void
    {
        if ($min <= $this->priceData->getPrice()) {
            $this->priceData->setSpecialFromDate("");
            $this->priceData->setSpecialToDate("");
            $this->priceData->setPrice(0); // will be reset just after
        }

        $this->priceData->setMaxPrice((float) $max);

        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                if ($min !== $max && $min <= $this->priceData->getPrice($groupId)) {
                    $this->priceData->setPrice(0, $groupId);
                }
                $this->priceData->setMaxPrice((float) $max, $groupId);
            }
        }
    }

    protected function handleZeroDefaultPrice($currencyCode, $min, $max): void
    {

    }

    protected function setFinalGroupPrices(
        $currencyCode,
        $min,
        $product,
        $subProducts,
        $withTax
    ) : void
    {
        $subProductsMinArray = count($subProducts) > 0 ?
            $this->formatMinArray($product, $subProducts, $min, $currencyCode, $withTax) :
            [];

        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');

            if (!empty($subProductsMinArray)) {
                $this->priceData->setPrice($subProductsMinArray[$groupId]['price'], $groupId);
                $this->priceData->setMaxPrice((float) $subProductsMinArray[$groupId]['price_max'], $groupId);
            } else {
                if ($this->priceData->getPrice($groupId) == 0) {
                    $this->priceData->setPrice($min, $groupId);
                }
            }
        }
    }

    protected function formatMinArray($product, $subProducts, $min, $currencyCode, $withTax): array
    {
        $minArray = [];
        $groupPriceList = $this->getGroupPriceList($product, $subProducts, $min, $currencyCode, $withTax);

        foreach ($groupPriceList as $key => $value) {
            $minArray[$key]['price'] = $value['min'];
            $minArray[$key]['price_max'] = $value['max'];
        }

        return $minArray;
    }

    protected function getGroupPriceList($product, $subProducts, $min, $currencyCode, $withTax): array
    {
        $groupPriceList = [];
        $subProductsMin = self::PRICE_NOT_SET;
        $subProductsMax = self::PRICE_NOT_SET;
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $minPrice = $min;

            foreach ($subProducts as $subProduct) {
                $subProduct->setData('customer_group_id', $groupId);
                $subProduct->setData('website_id', $subProduct->getStore()->getWebsiteId());

                $specialPrice = $this->getSpecialPrice($subProduct, $currencyCode, $withTax, []);
                $tierPrice = $this->getTierPrice($subProduct, $currencyCode, $withTax);
                $price = $this->pricingHelper->getTaxPrice(
                    $product,
                    $subProduct->getPriceModel()->getFinalPrice(1, $subProduct),
                    $withTax
                )
                ;

                if (!empty($tierPrice[$groupId]) && $specialPrice[$groupId] > $tierPrice[$groupId]) {
                    $minPrice = $tierPrice[$groupId];
                }

                if ($subProductsMin === self::PRICE_NOT_SET || $price < $subProductsMin) {
                    $subProductsMin = $price;
                }

                if ($subProductsMax === self::PRICE_NOT_SET || $price > $subProductsMax) {
                    $subProductsMax = $price;
                }

                $groupPriceList[$groupId]['min'] = min($minPrice, $subProductsMin);
                $groupPriceList[$groupId]['max'] = $subProductsMax;
                $subProduct->setData('customer_group_id', null);
            }

            $subProductsMin = self::PRICE_NOT_SET;
            $subProductsMax = self::PRICE_NOT_SET;
        }

        return $groupPriceList;
    }
}
