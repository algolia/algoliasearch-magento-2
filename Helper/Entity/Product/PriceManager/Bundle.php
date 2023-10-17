<?php

namespace Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager;

use Magento\Bundle\Model\Product\Price;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;

class Bundle extends ProductWithChildren
{
    /**
     * Overide parent addAdditionalData function
     * @param $product
     * @param $withTax
     * @param $subProducts
     * @param $currencyCode
     * @param $field
     * @return void
     */
    protected function addAdditionalData($product, $withTax, $subProducts, $currencyCode, $field) {
        $data = $this->getMinMaxPrices($product, $withTax, $subProducts, $currencyCode);
        $dashedFormat = $this->getDashedPriceFormat($data['min_price'], $data['max_price'], $currencyCode);
        if ($data['min_price'] !== $data['max_price']) {
            $this->handleBundleNonEqualMinMaxPrices($field, $currencyCode, $data['min_price'], $data['max_price'], $dashedFormat);
        }

        $this->handleOriginalPrice($field, $currencyCode, $data['min_price'], $data['max_price'], $data['min_original'], $data['max_original']);
        if (!$this->customData[$field][$currencyCode]['default']) {
            $this->handleZeroDefaultPrice($field, $currencyCode, $data['min_price'], $data['max_price']);
        }
        if ($this->areCustomersGroupsEnabled) {
            $groupedDashedFormat = $this->getBundleDashedPriceFormat($data['min'], $data['max'], $currencyCode);
            $this->setFinalGroupPricesBundle($field, $currencyCode, $data['min'], $data['max'], $groupedDashedFormat);
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
        $productWithPrice = $this->productloader->create()->load($product->getId());
        $productWithPrice->setData('website_id', $product->getStore()->getWebsiteId());
        $minPrice = $productWithPrice->getPriceInfo()->getPrice('final_price')->getMinimalPrice()->getValue();
        $minOriginalPrice = $productWithPrice->getPriceInfo()->getPrice('regular_price')->getMinimalPrice()->getValue();
        $maxOriginalPrice = $productWithPrice->getPriceInfo()->getPrice('regular_price')->getMaximalPrice()->getValue();
        $max = $productWithPrice->getPriceInfo()->getPrice('final_price')->getMaximalPrice()->getValue();
        $minArray = [];
        $maxArray = [];
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $productWithPrice->setData('customer_group_id', $groupId);
            $minPrice = $productWithPrice->getPriceInfo()->getPrice('final_price')->getMinimalPrice()->getValue();
            $minArray[$groupId] = $productWithPrice->getPriceInfo()->getPrice('final_price')->getMinimalPrice()->getValue();
            $maxArray[$groupId] = $productWithPrice->getPriceInfo()->getPrice('final_price')->getMaximalPrice()->getValue();
            $productWithPrice->setData('customer_group_id', null);
        }

        $minPriceArray = [];
        foreach ($minArray as $groupId => $min) {
            $minPriceArray[$groupId] = $min;
        }
        $maxPriceArray = [];
        foreach ($maxArray as $groupId => $max) {
            $maxPriceArray[$groupId] = $max;
        }

        if ($currencyCode !== $this->baseCurrencyCode) {
            $minPrice = $this->convertPrice($minPrice, $currencyCode);
            $minOriginalPrice = $this->convertPrice($minOriginalPrice, $currencyCode);
            $maxOriginalPrice = $this->convertPrice($maxOriginalPrice, $currencyCode);
            foreach ($minPriceArray as $groupId => $price) {
                $minPriceArray[$groupId] = $this->convertPrice($price, $currencyCode);
            }
            if ($minPrice !== $max) {
                $max = $this->convertPrice($max, $currencyCode);
            }
        }
        return [
            'min' => $minPriceArray,
            'max' => $maxPriceArray,
            'min_price' => $minPrice,
            'max_price' => $max,
            'min_original' => $minOriginalPrice,
            'max_original' => $maxOriginalPrice
        ];
    }

    /**
     * @param $field
     * @param $currencyCode
     * @param $min
     * @param $max
     * @param $dashedFormat
     * @return void
     */
    protected function handleBundleNonEqualMinMaxPrices($field, $currencyCode, $min, $max, $dashedFormat) {
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
    }

    /**
     * @param $minPrices
     * @param $max
     * @param $currencyCode
     * @return array
     */
    protected function getBundleDashedPriceFormat($minPrices, $max, $currencyCode) {
        $dashedFormatPrice = [];
        foreach ($minPrices as $groupId => $min) {
            if ($min === $max[$groupId]) {
                $dashedFormatPrice [$groupId] =  '';
            }
            $dashedFormatPrice[$groupId] = $this->formatPrice($min, $currencyCode) . ' - ' . $this->formatPrice($max[$groupId], $currencyCode);
        }
        return $dashedFormatPrice;
    }

    /**
     * @param $field
     * @param $currencyCode
     * @param $min
     * @param $max
     * @param $dashedFormat
     * @return void
     */
    protected function setFinalGroupPricesBundle($field, $currencyCode, $min, $max, $dashedFormat)
    {
        /** @var \Magento\Customer\Model\Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $this->customData[$field][$currencyCode]['group_' . $groupId] = $min[$groupId];
            if ($min[$groupId] === $max[$groupId]) {
                $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] =
                    $this->customData[$field][$currencyCode]['default_formated'];
            } else {
                $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] = $dashedFormat[$groupId];
            }
        }
    }
}
