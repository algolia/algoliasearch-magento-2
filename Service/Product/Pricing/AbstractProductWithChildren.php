<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterface;
use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterfaceFactory;
use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\Product\PriceDataFormatter;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;

abstract class AbstractProductWithChildren extends AbstractProduct
{
    public const int PRICE_NOT_SET = -1;

    public function __construct(
        protected MinMaxPricesInterfaceFactory $minMaxPricesFactory,
        protected ConfigHelper $configHelper,
        protected PricingHelper $pricingHelper,
        protected DiagnosticsLogger $logger,
        protected PriceDataFormatter $priceDataFormatter
    ) {
        parent::__construct(
            $configHelper,
            $pricingHelper,
            $logger,
            $priceDataFormatter
        );
    }

    protected function addComplexPricing(PriceDataInterface $priceData, PricingContextInterface $pricingContext)
    : PriceDataInterface
    {
        $minMaxPrices = $this->getChildrenMinMaxPrices($pricingContext);

        $priceData = $this->addOriginalPrice($priceData, $minMaxPrices);
        $priceData = $this->addPriceRange($priceData, $pricingContext, $minMaxPrices);
        $priceData = $this->addChildrenCustomerGroupsPrices($priceData, $pricingContext, $minMaxPrices);

        return $priceData;
    }

    protected function getChildrenMinMaxPrices(PricingContextInterface $pricingContext): MinMaxPricesInterface
    {
        $subProducts = $pricingContext->getSubProducts();

        $min      = PHP_FLOAT_MAX;
        $max      = 0.0;
        $original = $min;
        $originalMax = $max;
        if (count($subProducts) > 0) {
            /** @var Product $subProduct */
            foreach ($subProducts as $subProduct) {
                [$price, $basePrice] = $this->getSubProductPrices($pricingContext, $subProduct);

                $min = min($min, $price);
                $original = min($original, $basePrice);
                $max = max($max, $price);
                $originalMax = max($originalMax, $basePrice);
            }
        } else {
            $originalMax = $original = $min = $max;
        }

        return $this->minMaxPricesFactory->create([
            'min' => $min,
            'max' => $max,
            'minOriginal' => $original,
            'maxOriginal' => $originalMax,
        ]);
    }

    protected function getSubProductPrices(PricingContextInterface $pricingContext, Product $subProduct): array
    {
        $product = $pricingContext->getProduct();
        $specialPrice = $this->getSpecialPrice($pricingContext, $subProduct);
        $tierPrice = $this->getTierPrice($pricingContext, $subProduct);
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

        return [$price, $basePrice];
    }

    protected function addOriginalPrice(
        PriceDataInterface $priceData,
        MinMaxPricesInterface $minMaxPrices
    ): PriceDataInterface
    {
        $min = $minMaxPrices->getMin();
        $max = $minMaxPrices->getMax();
        $maxOriginal = $minMaxPrices->getMaxOriginal();

        if ($max < $maxOriginal) {
            $priceData->setOriginalPrice($maxOriginal);
        }

        if ($priceData->getPrice() === 0.00) {
            $priceData->setPrice($min);
        }

        return $priceData;
    }

    protected function addPriceRange(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        MinMaxPricesInterface $minMaxPrices
    ): PriceDataInterface
    {
        $min = $minMaxPrices->getMin();
        $max = $minMaxPrices->getMax();

        if ($min === $max) {
            return $priceData;
        }

        $priceData->setMinPrice($min);
        $priceData->setMaxPrice($max);

        if ($pricingContext->areCustomerGroupsEnabled()) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                $priceData->setMinPrice($min, $groupId);
                $priceData->setMaxPrice($max, $groupId);
            }
        }

        return $priceData;
    }

    protected function addChildrenCustomerGroupsPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        MinMaxPricesInterface $minMaxPrices
    ): PriceDataInterface
    {
        if (!$pricingContext->areCustomerGroupsEnabled()) {
            return $priceData;
        }

        $min = $minMaxPrices->getMin();
        $max = $minMaxPrices->getMax();
        $maxOriginal = $minMaxPrices->getMaxOriginal();

        if ($max < $maxOriginal) {
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                $priceData->setOriginalPrice($maxOriginal, $groupId);
            }
        }

        $priceData = $this->addCustomerGroupsFinalPrices($priceData, $pricingContext, $min);

        return $priceData;
    }

    protected function addCustomerGroupsFinalPrices(
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

                $specialPrice = $this->getSpecialPrice($pricingContext, $subProduct);
                $tierPrice = $this->getTierPrice($pricingContext, $subProduct);
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
