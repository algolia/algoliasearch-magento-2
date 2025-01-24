<?php

namespace Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;

abstract class ProductWithChildren extends ProductWithoutChildren
{
    /**
     * @param $product
     * @param $withTax
     * @param $subProducts
     * @param $currencyCode
     * @param $field
     * @return void
     */
    protected function addAdditionalData($product, $withTax, $subProducts, $currencyCode, $field)
    {
        list($min, $max, $minOriginal, $maxOriginal) =
            $this->getMinMaxPrices($product, $withTax, $subProducts, $currencyCode);
        $dashedFormat = $this->getDashedPriceFormat($min, $max, $currencyCode);
        if ($min !== $max) {
            $this->handleNonEqualMinMaxPrices($field, $currencyCode, $min, $max, $dashedFormat);
        }
        $this->handleOriginalPrice($field, $currencyCode, $min, $max, $minOriginal, $maxOriginal);
        if (!$this->customData[$field][$currencyCode]['default']) {
            $this->handleZeroDefaultPrice($field, $currencyCode, $min, $max);
        }
        if ($this->areCustomersGroupsEnabled) {
            $this->setFinalGroupPrices($field, $currencyCode, $min, $max, $dashedFormat, $product, $subProducts, $withTax);
        }
    }

    /**
     * @param Product $product
     * @param $withTax
     * @param $subProducts
     * @param $currencyCode
     * @return array
     */
    protected function getMinMaxPrices(Product $product, $withTax, $subProducts, $currencyCode)
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
                $price     = $minPrice ?? $this->getTaxPrice($product, $subProduct->getFinalPrice(), $withTax);
                $basePrice = $this->getTaxPrice($product, $subProduct->getPrice(), $withTax);
                $min = min($min, $price);
                $original = min($original, $basePrice);
                $max = max($max, $price);
                $originalMax = max($originalMax, $basePrice);
            }
        } else {
            $originalMax = $original = $min = $max;
        }
        if ($currencyCode !== $this->baseCurrencyCode) {
            $min      = $this->convertPrice($min, $currencyCode);
            $original = $this->convertPrice($original, $currencyCode);
            if ($min !== $max) {
                $max = $this->convertPrice($max, $currencyCode);
                $originalMax = $this->convertPrice($originalMax, $currencyCode);
            }
        }
        return [$min, $max, $original, $originalMax];
    }

    /**
     * @param $min
     * @param $max
     * @param $currencyCode
     * @return string
     */
    protected function getDashedPriceFormat($min, $max, $currencyCode): string
    {
        if ($min === $max) {
            return '';
        }
        return $this->formatPrice($min, $currencyCode) . ' - ' . $this->formatPrice($max, $currencyCode);
    }

    /**
     * @param $field
     * @param $currencyCode
     * @param $min
     * @param $max
     * @param $dashedFormat
     * @return void
     */
    protected function handleNonEqualMinMaxPrices($field, $currencyCode, $min, $max, $dashedFormat)
    {
        if (isset($this->customData[$field][$currencyCode]['default_original_formated']) === false
            || $min <= $this->customData[$field][$currencyCode]['default']) {
            $this->customData[$field][$currencyCode]['default_formated'] = $dashedFormat;
            //// Do not keep special price that is already taken into account in min max
            unset(
                $this->customData['price']['special_from_date'],
                $this->customData['price']['special_to_date'],
                $this->customData['price']['default_original_formated']
            );
            $this->customData[$field][$currencyCode]['default'] = 0; // will be reset just after
        }

        $this->customData[$field][$currencyCode]['default_max'] = $max;
        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                if ($min !== $max && $min <= $this->customData[$field][$currencyCode]['group_' . $groupId]) {
                    $this->customData[$field][$currencyCode]['group_' . $groupId]               = 0;
                    $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] = $dashedFormat;
                }
                $this->customData[$field][$currencyCode]['group_' . $groupId . '_max'] = $max;
            }
        }
    }

    /**
     * @param $field
     * @param $currencyCode
     * @param $min
     * @param $max
     * @return void
     */
    protected function handleZeroDefaultPrice($field, $currencyCode, $min, $max)
    {
        $this->customData[$field][$currencyCode]['default'] = $min;
        if ($min !== $max) {
            return;
        }
        $this->customData[$field][$currencyCode]['default']          = $min;
        $this->customData[$field][$currencyCode]['default_formated'] = $this->formatPrice($min, $currencyCode);
    }

    /**
     * @param $field
     * @param $currencyCode
     * @param $min
     * @param $max
     * @param $dashedFormat
     * @param $product
     * @param $subproducts
     * @param $withTax
     * @return void
     */
    protected function setFinalGroupPrices($field, $currencyCode, $min, $max, $dashedFormat, $product, $subproducts, $withTax)
    {
        if (count($subproducts) > 0) {
            $groupPriceList = [];
            $subProductsMin = -1;
            $subProductsMax = 0;
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                $minPrice = $min;

                foreach ($subproducts as $subProduct) {
                    $subProduct->setData('customer_group_id', $groupId);
                    $subProduct->setData('website_id', $subProduct->getStore()->getWebsiteId());
                    $specialPrice = $this->getSpecialPrice($subProduct, $currencyCode, $withTax, []);
                    $tierPrice = $this->getTierPrice($subProduct, $currencyCode, $withTax);
                    $price     = $this->getTaxPrice($product, $subProduct->getPriceModel()->getFinalPrice(1, $subProduct), $withTax);

                    if (!empty($tierPrice[$groupId]) && $specialPrice[$groupId] > $tierPrice[$groupId]){
                        $minPrice = $tierPrice[$groupId];
                    }

                    if (isset($tierPrice[$groupId]) && $tierPrice[$groupId] !== false) {
                        $minPrice = $tierPrice[$groupId];
                    }

                    if ($subProductsMin == -1 || $price < $subProductsMin) {
                        $subProductsMin = $price;
                    }

                    if ($price > $subProductsMax) {
                        $subProductsMax = $price;
                    }

                    $groupPriceList[$groupId]['min'] = min($minPrice, $subProductsMin);
                    $groupPriceList[$groupId]['max'] = $subProductsMax;
                    $subProduct->setData('customer_group_id', null);

                }

                $subProductsMin = -1;
                $subProductsMax = 0;
            }

            $minArray = [];
            foreach ($groupPriceList as $key => $value) {
                $minArray[$key]['price'] = $value['min'];
                $minArray[$key]['price_max'] = $value['max'];
                $minArray[$key]['formatted'] = $this->formattedConfigPrice($value['min'], $value['max'], $currencyCode);
                if ($currencyCode !== $this->baseCurrencyCode) {
                    $minArray[$key]['formatted'] = $this->formattedConfigPrice($value['min'], $value['max'], $currencyCode);
                }
            }
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                $this->customData[$field][$currencyCode]['group_' . $groupId] = $minArray[$groupId]['price'];
                $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] = $minArray[$groupId]['formatted'];
                $this->customData[$field][$currencyCode]['group_' . $groupId . '_max'] = $minArray[$groupId]['price_max'];
            }
        } else {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                if ($this->customData[$field][$currencyCode]['group_' . $groupId] == 0) {
                    $this->customData[$field][$currencyCode]['group_' . $groupId] = $min;
                    if ($min === $max) {
                        $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] =
                            $this->customData[$field][$currencyCode]['default_formated'];
                    } else {
                        $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] = $dashedFormat;
                    }
                }
            }
        }
    }

    /**
     * @param $min
     * @param $max
     * @param $currencyCode
     * @return mixed|string
     */
    public function formattedConfigPrice($min, $max, $currencyCode) {
        if ($min != $max) {
            return $this->getDashedPriceFormat($min, $max, $currencyCode);
        } else {
            return $this->formatPrice($min, $currencyCode);
        }
    }

    /**
     * @param $field
     * @param $currencyCode
     * @param $min
     * @param $max
     * @param $minOriginal
     * @param $maxOriginal
     * @return void
     */
    public function handleOriginalPrice($field, $currencyCode, $min, $max, $minOriginal, $maxOriginal)
    {
        if ($min !== $max) {
            if ($min !== $minOriginal || $max !== $maxOriginal) {
                if ($minOriginal !== $maxOriginal) {
                    $this->customData[$field][$currencyCode]['default_original_formated'] = $this->getDashedPriceFormat(
                        $minOriginal,
                        $maxOriginal,
                        $currencyCode
                    );
                    $this->handleGroupOrginalPriceformated($field, $currencyCode, $this->customData[$field][$currencyCode]['default_original_formated']);
                } else {
                    $this->customData[$field][$currencyCode]['default_original_formated'] = $this->formatPrice(
                        $minOriginal,
                        $currencyCode
                    );
                    $this->handleGroupOrginalPriceformated($field, $currencyCode, $this->customData[$field][$currencyCode]['default_original_formated']);
                }
            }
        } else {
            if ($min < $minOriginal) {
                $this->customData[$field][$currencyCode]['default_original_formated'] = $this->formatPrice(
                    $minOriginal,
                    $currencyCode
                );
                $this->handleGroupOrginalPriceformated($field, $currencyCode, $this->customData[$field][$currencyCode]['default_original_formated']);
            }
        }

    }

    /**
     * @param $field
     * @param $currencyCode
     * @param $formatedPrice
     * @return void
     */
    public function handleGroupOrginalPriceformated($field, $currencyCode, $formatedPrice) {
        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int)$group->getData('customer_group_id');
                $this->customData[$field][$currencyCode]['group_' . $groupId . '_original_formated'] =
                    $formatedPrice;
            }
        }
    }
}
