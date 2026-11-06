<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterface;
use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Api\Product\PricingStrategyInterface;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups\CustomerGroupsManager;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;

/**
 * Pricing strategy when customer groups are ENABLED
 * Handles multi-group price calculations with iterations
 */
class CustomerGroupPricingStrategy implements PricingStrategyInterface
{
    public const int PRICE_NOT_SET = -1;

    public function __construct(
        protected PricingHelper $pricingHelper,
        protected CustomerGroupsManager $customerGroupsManager
    ) {}

    public function applyCustomerGroupPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext
    ): PriceDataInterface {
        $product = $pricingContext->getProduct();
        $groups = $this->customerGroupsManager->getCustomerGroups($pricingContext);

        /** @var Group $group */
        foreach ($groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $product->setData('customer_group_id', $groupId);
            $product->setData('website_id', $product->getStore()->getWebsiteId());
            $discountedPrice = $product->getPriceInfo()->getPrice('final_price')->getValue();
            $discountedPrice = $this->pricingHelper->handleConversion($discountedPrice, $pricingContext);

            if ($discountedPrice) {
                $priceData->setPrice(
                    $this->pricingHelper->getTaxPrice($product, $discountedPrice, $pricingContext->shouldIncludeTax()),
                    $groupId
                );

                if ($priceData->getPrice() > $priceData->getPrice($groupId)) {
                    $priceData->setOriginalPrice($priceData->getPrice(), $groupId);
                }
            } else {
                $priceData->setPrice($priceData->getPrice(), $groupId);
            }
        }

        $product->setData('customer_group_id', null);

        return $priceData;
    }

    public function applySpecialPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        array $specialPricesByGroup
    ): PriceDataInterface {
        $groups = $this->customerGroupsManager->getCustomerGroups($pricingContext);

        /** @var Group $group */
        foreach ($groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            if ($specialPricesByGroup[$groupId]) {
                $roundedSpecialPrice = $this->pricingHelper->round($specialPricesByGroup[$groupId]);

                if ($roundedSpecialPrice < $priceData->getPrice($groupId)) {
                    $priceData->setPrice($roundedSpecialPrice, $groupId);

                    if ($priceData->getPrice() > $priceData->getPrice($groupId)) {
                        $priceData->setOriginalPrice($priceData->getPrice(), $groupId);
                    }
                }
            }
        }

        // Also apply default group special price
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
        $groups = $this->customerGroupsManager->getCustomerGroups($pricingContext);

        /** @var Group $group */
        foreach ($groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');

            if (isset($tierPricesByGroup[$groupId]) && $tierPricesByGroup[$groupId]) {
                $priceData->setTierPrice($this->pricingHelper->round($tierPricesByGroup[$groupId]), $groupId);
            }
        }

        // Also apply default group tier price
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

        $priceData->setMinPrice($min);
        $priceData->setMaxPrice($max);

        $groups = $this->customerGroupsManager->getCustomerGroups($pricingContext);

        /** @var Group $group */
        foreach ($groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $priceData->setMinPrice($min, $groupId);
            $priceData->setMaxPrice($max, $groupId);
        }

        return $priceData;
    }

    public function applyChildrenCustomerGroupPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        MinMaxPricesInterface $minMaxPrices
    ): PriceDataInterface {
        $max = $minMaxPrices->getMax();
        $maxOriginal = $minMaxPrices->getMaxOriginal();

        if ($max < $maxOriginal) {
            $groups = $this->customerGroupsManager->getCustomerGroups($pricingContext);
            foreach ($groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                $priceData->setOriginalPrice($maxOriginal, $groupId);
            }
        }

        return $priceData;
    }

    public function applyCustomerGroupsFinalPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        float $min,
        bool $useBundleCalculation = false
    ): PriceDataInterface {
        $groups = $this->customerGroupsManager->getCustomerGroups($pricingContext);
        $subProducts = $pricingContext->getSubProducts();
        $groupPriceList = $useBundleCalculation ?
            $this->getBundleGroupPriceList($pricingContext, $min, $subProducts) :
            $this->getGroupPriceList($pricingContext, $min, $subProducts);

        $subProductsMinArray = count($subProducts) > 0 ?
            $this->formatMinArray($pricingContext, $groupPriceList) :
            [];

        foreach ($groups as $group) {
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

    protected function formatMinArray(PricingContextInterface $pricingContext, array $groupPriceList): array
    {
        $minArray = [];

        foreach ($groupPriceList as $key => $value) {
            $minArray[$key]['price'] = $value['min'];
            $minArray[$key]['price_max'] = $value['max'];
        }

        return $minArray;
    }

    protected function getGroupPriceList(
        PricingContextInterface $pricingContext,
        float $min,
        array $subProducts
    ): array {
        $groups = $this->customerGroupsManager->getCustomerGroups($pricingContext);
        $groupPriceList = [];

        $subProductsMin = self::PRICE_NOT_SET;
        $subProductsMax = self::PRICE_NOT_SET;

        /** @var Group $group */
        foreach ($groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $minPrice = $min;

            foreach ($subProducts as $subProduct) {
                $subProduct->setData('customer_group_id', $groupId);
                $subProduct->setData('website_id', $subProduct->getStore()->getWebsiteId());

                $price = $this->pricingHelper->getTaxPrice(
                    $pricingContext->getProduct(),
                    $subProduct->getPriceModel()->getFinalPrice(1, $subProduct),
                    $pricingContext->shouldIncludeTax()
                );

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

    protected function getBundleGroupPriceList(PricingContextInterface $pricingContext, $min): array
    {
        $groups = $this->customerGroupsManager->getCustomerGroups($pricingContext);
        $subProducts = $pricingContext->getSubProducts();

        $groupPriceList = [];

        /** @var Group $group */
        foreach ($groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $options = [];

            foreach ($subProducts as $subProduct) {
                $subProduct->setData('customer_group_id', $groupId);
                $subProduct->setData('website_id', $subProduct->getStore()->getWebsiteId());

                $price = $this->pricingHelper->getTaxPrice(
                    $pricingContext->getProduct(),
                    $subProduct->getPriceModel()->getFinalPrice(1, $subProduct),
                    $pricingContext->shouldIncludeTax()
                );

                $options[$subProduct->getOptionId()][] = $price;
                $subProduct->setData('customer_group_id', null);
            }

            $groupPriceList[$groupId]['min'] = $groupPriceList[$groupId]['max'] = 0.00;

            foreach ($options as $optionsValues) {
                $groupPriceList[$groupId]['min'] += min($optionsValues);
                $groupPriceList[$groupId]['max'] += max($optionsValues);
            }

            $groupPriceList[$groupId]['min'] =
                $this->pricingHelper->handleConversion($groupPriceList[$groupId]['min'], $pricingContext);
            $groupPriceList[$groupId]['max'] =
                $this->pricingHelper->handleConversion($groupPriceList[$groupId]['max'], $pricingContext);

        }

        return $groupPriceList;
    }
}
