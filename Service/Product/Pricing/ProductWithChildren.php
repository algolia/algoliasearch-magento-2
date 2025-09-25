<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;

abstract class ProductWithChildren extends ProductWithoutChildren
{
    const PRICE_NOT_SET = -1;

    /**
     * @param $priceData
     * @param $product
     * @param $withTax
     * @param $subProducts
     * @param $currencyCode
     * @return array
     */
    protected function addAdditionalData($priceData, $product, $withTax, $subProducts, $currencyCode): array
    {
        [$min, $max, $minOriginal, $maxOriginal] =
            $this->getMinMaxPrices($product, $withTax, $subProducts, $currencyCode);

        $dashedFormat = $this->pricingHelper->formatDashedPriceFormat($min, $max, $this->store, $currencyCode);

        if ($min !== $max) {
            $priceData = $this->handleNonEqualMinMaxPrices($priceData, $currencyCode, $min, $max, $dashedFormat);
        }

        $priceData = $this->handleOriginalPrice($priceData, $currencyCode, $min, $max, $minOriginal, $maxOriginal);
        if (!$priceData[$currencyCode]['default']) {
            $priceData = $this->handleZeroDefaultPrice($priceData, $currencyCode, $min, $max);
        }
        if ($this->areCustomersGroupsEnabled) {
            $priceData = $this->setFinalGroupPrices($priceData, $currencyCode, $min, $max, $dashedFormat, $product, $subProducts, $withTax);
        }

        return $priceData;
    }

    /**
     * @param Product $product
     * @param $withTax
     * @param $subProducts
     * @param $currencyCode
     * @return array
     */
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

                $price     = $minPrice ?? $this->pricingHelper->getTaxPrice($product, $finalPrice, $withTax);
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

    /**
     * @param $priceData
     * @param $currencyCode
     * @param $min
     * @param $max
     * @param $dashedFormat
     * @return array
     */
    protected function handleNonEqualMinMaxPrices($priceData, $currencyCode, $min, $max, $dashedFormat): array
    {
        if (isset($priceData[$currencyCode]['default_original_formated']) === false
            || $min <= $priceData[$currencyCode]['default']) {
            $priceData[$currencyCode]['default_formated'] = $dashedFormat;

            //// Do not keep special price that is already taken into account in min max
            unset(
                $priceData['special_from_date'],
                $priceData['special_to_date'],
                $priceData['default_original_formated']
            );
            $priceData[$currencyCode]['default'] = 0; // will be reset just after
        }

        $priceData[$currencyCode]['default_max'] = $max;

        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                if ($min !== $max && $min <= $priceData[$currencyCode]['group_' . $groupId]) {
                    $priceData[$currencyCode]['group_' . $groupId]               = 0;
                    $priceData[$currencyCode]['group_' . $groupId . '_formated'] = $dashedFormat;
                }
                $priceData[$currencyCode]['group_' . $groupId . '_max'] = $max;
            }
        }

        return $priceData;
    }

    /**
     * @param $priceData
     * @param $currencyCode
     * @param $min
     * @param $max
     * @return array
     */
    protected function handleZeroDefaultPrice($priceData, $currencyCode, $min, $max): array
    {
        $priceData[$currencyCode]['default'] = $min;

        if ($min !== $max) {
            return $priceData;
        }
        $priceData[$currencyCode]['default'] = $min;
        $priceData[$currencyCode]['default_formated'] =
            $this->pricingHelper->formatPrice($min, $this->store, $currencyCode);

        return $priceData;
    }

    /**
     * @param $priceData
     * @param $currencyCode
     * @param $min
     * @param $max
     * @param $dashedFormat
     * @param $product
     * @param $subProducts
     * @param $withTax
     * @return array
     */
    protected function setFinalGroupPrices(
        $priceData,
        $currencyCode,
        $min,
        $max,
        $dashedFormat,
        $product,
        $subProducts,
        $withTax)
    : array
    {
        $subProductsMinArray = count($subProducts) > 0 ?
            $this->formatMinArray($product, $subProducts, $min, $currencyCode, $withTax) :
            [];

        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');

            if (!empty($subProductsMinArray)) {
                $priceData[$currencyCode]['group_' . $groupId] = $subProductsMinArray[$groupId]['price'];
                $priceData[$currencyCode]['group_' . $groupId . '_formated'] = $subProductsMinArray[$groupId]['formatted'];
                $priceData[$currencyCode]['group_' . $groupId . '_max'] = $subProductsMinArray[$groupId]['price_max'];
            } else {
                if ($priceData[$currencyCode]['group_' . $groupId] == 0) {
                    $priceData[$currencyCode]['group_' . $groupId] = $min;
                    if ($min === $max) {
                        $priceData[$currencyCode]['group_' . $groupId . '_formated'] =
                            $priceData[$currencyCode]['default_formated'];
                    } else {
                        $priceData[$currencyCode]['group_' . $groupId . '_formated'] = $dashedFormat;
                    }
                }
            }
        }

        return $priceData;
    }

    /**
     * @param $product
     * @param $subProducts
     * @param $min
     * @param $currencyCode
     * @param $withTax
     * @return array
     */
    protected function formatMinArray($product, $subProducts, $min, $currencyCode, $withTax): array
    {
        $minArray = [];
        $groupPriceList = $this->getGroupPriceList($product, $subProducts, $min, $currencyCode, $withTax);

        foreach ($groupPriceList as $key => $value) {
            $minArray[$key]['price'] = $value['min'];
            $minArray[$key]['price_max'] = $value['max'];
            $minArray[$key]['formatted'] = $this->pricingHelper->formattedConfigPrice(
                $value['min'],
                $value['max'],
                $this->store,
                $currencyCode
            );

            if ($currencyCode !== $this->baseCurrencyCode) {
                $minArray[$key]['formatted'] = $this->pricingHelper->formattedConfigPrice(
                    $value['min'],
                    $value['max'],
                    $this->store,
                    $currencyCode
                );
            }
        }

        return $minArray;
    }

    /**
     * @param $product
     * @param $subProducts
     * @param $min
     * @param $currencyCode
     * @param $withTax
     * @return array
     */
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
                    $withTax)
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

    /**
     * @param $priceData
     * @param $currencyCode
     * @param $min
     * @param $max
     * @param $minOriginal
     * @param $maxOriginal
     * @return array
     */
    public function handleOriginalPrice($priceData, $currencyCode, $min, $max, $minOriginal, $maxOriginal): array
    {
        if ($min !== $max) {
            if ($min !== $minOriginal || $max !== $maxOriginal) {
                if ($minOriginal !== $maxOriginal) {
                    $priceData[$currencyCode]['default_original_formated'] = $this->pricingHelper->formatDashedPriceFormat(
                        $minOriginal,
                        $maxOriginal,
                        $this->store,
                        $currencyCode
                    );
                    $priceData = $this->handleGroupOriginalPriceFormatted($priceData, $currencyCode);
                } else {
                    $priceData[$currencyCode]['default_original_formated'] = $this->pricingHelper->formatPrice(
                        $minOriginal,
                        $this->store,
                        $currencyCode
                    );
                    $priceData =$this->handleGroupOriginalPriceFormatted($priceData, $currencyCode);
                }
            }
        } else {
            if ($min < $minOriginal) {
                $priceData[$currencyCode]['default_original_formated'] = $this->pricingHelper->formatPrice(
                    $minOriginal,
                    $this->store,
                    $currencyCode
                );
                $priceData = $this->handleGroupOriginalPriceFormatted($priceData, $currencyCode);
            }
        }

        return $priceData;
    }

    /**
     * @param $priceData
     * @param $currencyCode
     * @return array
     */
    public function handleGroupOriginalPriceFormatted($priceData, $currencyCode): array
    {
        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int)$group->getData('customer_group_id');
                $priceData[$currencyCode]['group_' . $groupId . '_original_formated'] =
                    $priceData[$currencyCode]['default_original_formated'];
            }
        }

        return $priceData;
    }
}
