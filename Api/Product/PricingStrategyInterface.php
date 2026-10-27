<?php

namespace Algolia\AlgoliaSearch\Api\Product;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterface;
use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Magento\Catalog\Model\Product;

interface PricingStrategyInterface
{
    /**
     * Get customer groups to iterate over for price calculations
     */
    public function getCustomerGroupsForCalculation(PricingContextInterface $pricingContext): iterable;

    /**
     * Apply customer group specific prices (discounts based on group)
     */
    public function applyCustomerGroupPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext
    ): PriceDataInterface;

    /**
     * Apply special prices for customer groups
     */
    public function applySpecialPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        array $specialPricesByGroup
    ): PriceDataInterface;

    /**
     * Apply tier prices for customer groups
     */
    public function applyTierPrices(
        PriceDataInterface $priceData,
        array $tierPricesByGroup
    ): PriceDataInterface;

    /**
     * Apply price range (min/max) for products with children
     */
    public function applyPriceRange(
        PriceDataInterface $priceData,
        MinMaxPricesInterface $minMaxPrices
    ): PriceDataInterface;

    /**
     * Apply children customer group prices for complex products
     */
    public function applyChildrenCustomerGroupPrices(
        PriceDataInterface $priceData,
        MinMaxPricesInterface $minMaxPrices
    ): PriceDataInterface;

    /**
     * Apply final prices for customer groups on children products
     */
    public function applyCustomerGroupsFinalPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        float $min,
        bool $useBundleCalculation
    ): PriceDataInterface;
}
