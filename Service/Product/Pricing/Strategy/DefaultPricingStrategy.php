<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterface;
use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Api\Product\PricingStrategyInterface;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Magento\Catalog\Model\Product;

/**
 * Pricing strategy when customer groups are DISABLED
 * Operates only on default group (ID 0) with no loops
 */
class DefaultPricingStrategy implements PricingStrategyInterface
{
    public function __construct(
        protected PricingHelper $pricingHelper
    ) {}

    public function applyCustomerGroupPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext
    ): PriceDataInterface {
        // No customer group-specific prices when groups are disabled
        // Default price already set by addDefaultPrice()
        return $priceData;
    }

    public function applySpecialPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        array $specialPricesByGroup
    ): PriceDataInterface {
        // Only apply default group (ID 0) special price
        if ($specialPricesByGroup[0]) {
            $roundedSpecialPrice = $this->pricingHelper->round($specialPricesByGroup[0]);

            if ($roundedSpecialPrice < $priceData->getPrice()) {
                $priceData->setOriginalPrice($priceData->getPrice());
                $priceData->setPrice($roundedSpecialPrice);
            }
        }

        return $priceData;
    }

    public function applyTierPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        array $tierPricesByGroup
    ): PriceDataInterface {
        // Only apply default group (ID 0) tier price
        if (isset($tierPricesByGroup[0]) && $tierPricesByGroup[0]) {
            $priceData->setTierPrice($this->pricingHelper->round($tierPricesByGroup[0]));
        }

        return $priceData;
    }

    public function applyPriceRange(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        MinMaxPricesInterface $minMaxPrices
    ): PriceDataInterface {
        $min = $minMaxPrices->getMin();
        $max = $minMaxPrices->getMax();

        if ($min === $max) {
            return $priceData;
        }

        // Only set min/max for default group
        $priceData->setMinPrice($min);
        $priceData->setMaxPrice($max);

        return $priceData;
    }

    public function applyChildrenCustomerGroupPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        MinMaxPricesInterface $minMaxPrices
    ): PriceDataInterface {
        // No customer group-specific logic needed
        return $priceData;
    }

    public function applyCustomerGroupsFinalPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        float $min,
        bool $useBundleCalculation = false
    ): PriceDataInterface {
        // No customer group-specific final prices needed
        // Default price already set
        return $priceData;
    }
}
