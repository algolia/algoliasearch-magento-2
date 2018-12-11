<?php

namespace Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;

abstract class ProductWithChildren extends ProductWithoutChildren
{
    protected function addAdditionalData($product, $withTax, $subProducts, $currencyCode, $field)
    {
        list($min, $max) = $this->getMinMaxPrices($product, $withTax, $subProducts, $currencyCode);
        $dashedFormat = $this->getDashedPriceFormat($min, $max, $currencyCode);

        if ($min !== $max) {
            $this->handleNonEqualMinMaxPrices($field, $currencyCode, $min, $max, $dashedFormat);
        }

        if (!$this->customData[$field][$currencyCode]['default']) {
            $this->handleZeroDefaultPrice($field, $currencyCode, $min, $max);
        }

        if ($this->areCustomersGroupsEnabled) {
            $this->setFinalGroupPrices($field, $currencyCode, $min, $max, $dashedFormat);
        }
    }

    protected function getMinMaxPrices(Product $product, $withTax, $subProducts, $currencyCode)
    {
        $min = PHP_INT_MAX;
        $max = 0;

        if (count($subProducts) > 0) {
            /** @var Product $subProduct */
            foreach ($subProducts as $subProduct) {
                $price = $this->getTaxPrice($product, $subProduct->getFinalPrice(), $withTax);

                $min = min($min, $price);
                $max = max($max, $price);
            }
        } else {
            $min = $max;
        }

        if ($currencyCode !== $this->baseCurrencyCode) {
            $min = $this->convertPrice($min, $currencyCode);

            if ($min !== $max) {
                $max = $this->convertPrice($max, $currencyCode);
            }
        }

        return [$min, $max];
    }

    protected function getDashedPriceFormat($min, $max, $currencyCode)
    {
        if ($min === $max) {
            return '';
        }

        return $this->formatPrice($min, $currencyCode) . ' - ' . $this->formatPrice($max, $currencyCode);
    }

    protected function handleNonEqualMinMaxPrices($field, $currencyCode, $min, $max, $dashedFormat)
    {
        if (isset($this->customData[$field][$currencyCode]['default_original_formated']) === false
            || $min <= $this->customData[$field][$currencyCode]['default']) {
            $this->customData[$field][$currencyCode]['default_formated'] = $dashedFormat;

            //// Do not keep special price that is already taken into account in min max
            unset($this->customData['price']['special_from_date'], $this->customData['price']['special_to_date'], $this->customData['price']['default_original_formated']);

            $this->customData[$field][$currencyCode]['default'] = 0; // will be reset just after
        }

        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');

                if ($min !== $max && $min <= $this->customData[$field][$currencyCode]['group_' . $groupId]) {
                    $this->customData[$field][$currencyCode]['group_' . $groupId] = 0;
                    $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] = $dashedFormat;
                }
            }
        }
    }

    protected function handleZeroDefaultPrice($field, $currencyCode, $min, $max)
    {
        $this->customData[$field][$currencyCode]['default'] = $min;

        if ($min !== $max) {
            return;
        }

        $this->customData[$field][$currencyCode]['default'] = $min;
        $this->customData[$field][$currencyCode]['default_formated'] = $this->formatPrice($min, $currencyCode);
    }

    protected function setFinalGroupPrices($field, $currencyCode, $min, $max, $dashedFormat)
    {
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');

            if ($this->customData[$field][$currencyCode]['group_' . $groupId] === 0) {
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
