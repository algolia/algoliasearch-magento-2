<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;

abstract class AbstractProductWithChildren extends AbstractProduct
{
    public const PRICE_NOT_SET = -1;

    protected function addAdditionalData(PriceDataInterface $priceData, PricingContextInterface $pricingContext)
    : PriceDataInterface
    {
        [$min, $max, $minOriginal, $maxOriginal] =
            $this->getMinMaxPrices($pricingContext);

        if ($min !== $max) {
            $this->handleNonEqualMinMaxPrices($priceData, $pricingContext, $min, $max);
        }

        if ($max < $maxOriginal) {
            $priceData->setOriginalPrice($maxOriginal);
        }

        if ($priceData->getPrice() === 0.00) {
            $priceData->setPrice($min);
        }
        if ($pricingContext->areCustomerGroupsEnabled()) {
            $this->setFinalGroupPrices($priceData, $pricingContext, $min);
        }

        return $priceData;
    }

    protected function getMinMaxPrices(PricingContextInterface $pricingContext): array
    {
        $product = $pricingContext->getProduct();
        $subProducts = $pricingContext->getSubProducts();

        $min      = PHP_INT_MAX;
        $max      = 0;
        $original = $min;
        $originalMax = $max;
        if (count($subProducts) > 0) {
            /** @var Product $subProduct */
            foreach ($subProducts as $subProduct) {
                $specialPrice = $this->getSpecialPrice($pricingContext);
                $tierPrice = $this->getTierPrice($pricingContext);
                if (!empty($tierPrice[0]) && $specialPrice[0] > $tierPrice[0]){
                    $minPrice = $tierPrice[0];
                } else {
                    $minPrice = $specialPrice[0];
                }

                $finalPrice = $subProduct->getFinalPrice();
                $basePrice  = $subProduct->getPrice();

                if ($pricingContext->isCurrencyDifferentFromBase()) {
                    $finalPrice = $this->pricingHelper->convertPrice(
                        $finalPrice,
                        $pricingContext->getStore(),
                        $pricingContext->getCurrencyCode()
                    );
                    $basePrice  = $this->pricingHelper->convertPrice(
                        $basePrice,
                        $pricingContext->getStore(),
                        $pricingContext->getCurrencyCode()
                    );
                }

                $price = $minPrice ?? $this->pricingHelper->getTaxPrice($product, $finalPrice, $pricingContext->shouldIncludeTax());
                $basePrice = $this->pricingHelper->getTaxPrice($product, $basePrice, $pricingContext->shouldIncludeTax());

                if ($pricingContext->isFptEnabled()) {
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

    protected function handleNonEqualMinMaxPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        $min,
        $max
    ): PriceDataInterface
    {
        if ($min <= $priceData->getPrice()) {
            $priceData->setSpecialFromDate("");
            $priceData->setSpecialToDate("");
            $priceData->setPrice(0); // will be reset just after
        }

        $priceData->setMinPrice((float) $min);
        $priceData->setMaxPrice((float) $max);

        if ($pricingContext->areCustomerGroupsEnabled()) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                if ($min !== $max && $min <= $priceData->getPrice($groupId)) {
                    $priceData->setPrice(0, $groupId);
                }
                $priceData->setMinPrice((float) $min, $groupId);
                $priceData->setMaxPrice((float) $max, $groupId);
            }
        }

        return $priceData;
    }

    protected function handleZeroDefaultPrice(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        $min,
        $max)
    : PriceDataInterface
    {
        return $priceData;
    }

    protected function setFinalGroupPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        $min
    ) : PriceDataInterface
    {
        $subProducts = $pricingContext->getSubProducts();

        $subProductsMinArray = count($subProducts) > 0 ?
            $this->formatMinArray($pricingContext, $min) :
            [];

        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');

            if (!empty($subProductsMinArray)) {
                $priceData->setPrice($subProductsMinArray[$groupId]['price'], $groupId);
                $priceData->setMinPrice($subProductsMinArray[$groupId]['price'], $groupId);
                $priceData->setMaxPrice((float) $subProductsMinArray[$groupId]['price_max'], $groupId);
            } else {
                if ($priceData->getPrice($groupId) == 0) {
                    $priceData->setPrice($min, $groupId);
                }
            }
        }

        return $priceData;
    }

    protected function formatMinArray(PricingContextInterface $pricingContext, $min): array
    {
        $minArray = [];
        $groupPriceList = $this->getGroupPriceList($pricingContext, $min);

        foreach ($groupPriceList as $key => $value) {
            $minArray[$key]['price'] = $value['min'];
            $minArray[$key]['price_max'] = $value['max'];
        }

        return $minArray;
    }

    protected function getGroupPriceList(PricingContextInterface $pricingContext, $min): array
    {
        $subProducts = $pricingContext->getSubProducts();

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

                $specialPrice = $this->getSpecialPrice($pricingContext);
                $tierPrice = $this->getTierPrice($pricingContext);
                $price = $this->pricingHelper->getTaxPrice(
                    $pricingContext->getProduct(),
                    $subProduct->getPriceModel()->getFinalPrice(1, $subProduct),
                    $pricingContext->shouldIncludeTax()
                );

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
