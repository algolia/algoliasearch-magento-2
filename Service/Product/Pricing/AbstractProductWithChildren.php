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

        $dashedFormat = $this->pricingHelper->formatDashedPriceFormat($min, $max, $this->store, $currencyCode);

        if ($min !== $max) {
            $this->handleNonEqualMinMaxPrices($currencyCode, $min, $max, $dashedFormat);
        }

        $this->handleOriginalPrice($currencyCode, $min, $max, $minOriginal, $maxOriginal);
        if ($this->priceData->getPrice() === 0.00) {
            $this->handleZeroDefaultPrice($currencyCode, $min, $max);
        }
        if ($this->areCustomersGroupsEnabled) {
            $this->setFinalGroupPrices($currencyCode, $min, $max, $dashedFormat, $product, $subProducts, $withTax);
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

    protected function handleNonEqualMinMaxPrices($currencyCode, $min, $max, $dashedFormat): void
    {
        if ($this->priceData->getFormatedPrice() === "" || $min <= $this->priceData->getPrice()) {
            $this->priceData->setFormatedPrice($dashedFormat);
            $this->priceData->setSpecialFromDate("");
            $this->priceData->setSpecialToDate("");
            $this->priceData->setFormatedOriginalPrice("");
            $this->priceData->setPrice(0); // will be reset just after
        }

        $this->priceData->setMaxPrice($max);

        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                if ($min !== $max && $min <= $this->priceData->getPrice($groupId)) {
                    $this->priceData->setPrice(0, $groupId);
                    $this->priceData->setFormatedPrice($dashedFormat, $groupId);
                }
                $this->priceData->setMaxPrice($max, $groupId);
            }
        }
    }

    protected function handleZeroDefaultPrice($currencyCode, $min, $max): void
    {
        $this->priceData->setPrice($min);

        if ($min !== $max) {
            return;
        }

        $this->priceData->setFormatedPrice($this->pricingHelper->formatPrice($min, $this->store, $currencyCode));
    }

    protected function setFinalGroupPrices(
        $currencyCode,
        $min,
        $max,
        $dashedFormat,
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
                $this->priceData->setFormatedPrice($subProductsMinArray[$groupId]['formatted'], $groupId);
                $this->priceData->setMaxPrice($subProductsMinArray[$groupId]['price_max'], $groupId);
            } else {
                if ($this->priceData->getPrice($groupId) == 0) {
                    $this->priceData->setPrice($min, $groupId);
                    if ($min === $max) {
                        $this->priceData->setFormatedPrice($this->priceData->getFormatedPrice(), $groupId);
                    } else {
                        $this->priceData->setFormatedPrice($dashedFormat, $groupId);
                    }
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

    public function handleOriginalPrice($currencyCode, $min, $max, $minOriginal, $maxOriginal): void
    {
        if ($min !== $max) {
            if ($min !== $minOriginal || $max !== $maxOriginal) {
                if ($minOriginal !== $maxOriginal) {
                    $this->priceData->setFormatedOriginalPrice(
                        $this->pricingHelper->formatDashedPriceFormat(
                            $minOriginal,
                            $maxOriginal,
                            $this->store,
                            $currencyCode
                        )
                    );
                    $this->handleGroupOriginalPriceFormatted();
                } else {
                    $this->priceData->setFormatedOriginalPrice(
                        $this->pricingHelper->formatPrice(
                            $minOriginal,
                            $this->store,
                            $currencyCode
                        )
                    );
                    $this->handleGroupOriginalPriceFormatted();
                }
            }
        } else {
            if ($min < $minOriginal) {
                $this->priceData->setFormatedOriginalPrice(
                    $this->pricingHelper->formatPrice(
                        $minOriginal,
                        $this->store,
                        $currencyCode
                    )
                );
                $this->handleGroupOriginalPriceFormatted();
            }
        }
    }

    public function handleGroupOriginalPriceFormatted(): void
    {
        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                $this->priceData->setFormatedOriginalPrice($this->priceData->getFormatedOriginalPrice(), $groupId);
            }
        }
    }
}
