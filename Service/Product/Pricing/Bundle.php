<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;

class Bundle extends AbstractProductWithChildren
{
    protected function getChildrenMinMaxPrices(PricingContextInterface $pricingContext): MinMaxPricesInterface
    {
        $subProducts = $pricingContext->getSubProducts();

        $options = [];
        $optionsOriginal = [];
        $min = $max = $original = $originalMax = 0.00;

        if (count($subProducts) > 0) {
            /** @var Product $subProduct */
            foreach ($subProducts as $subProduct) {
                [$price, $basePrice] = $this->getSubProductPrices($pricingContext, $subProduct);

                $options[$subProduct->getOptionId()][] = $price;
                $optionsOriginal[$subProduct->getOptionId()][] = $basePrice;
            }
            // Addition of each option values (maximal and minimal amount combinations)
            foreach ($options as $optionsValues) {
                $min += min($optionsValues);
                $max += max($optionsValues);
            }
            // Same thing for original values
            foreach ($optionsOriginal as $optionOriginalValues) {
                $original += min($optionOriginalValues);
                $originalMax += max($optionOriginalValues);
            }
        }

        return $this->minMaxPricesFactory->create(
            [
                'data' => [
                    MinMaxPricesInterface::MIN => $min,
                    MinMaxPricesInterface::MAX => $max,
                    MinMaxPricesInterface::MIN_ORIGINAL => $original,
                    MinMaxPricesInterface::MAX_ORIGINAL => $originalMax,
                ]
            ]
        );
    }

    protected function getGroupPriceList(PricingContextInterface $pricingContext, $min): array
    {
        $subProducts = $pricingContext->getSubProducts();

        $groupPriceList = [];

        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $options = [];

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
                    $price = $tierPrice[$groupId];
                }

                $options[$subProduct->getOptionId()][] = $price;
                $subProduct->setData('customer_group_id', null);
            }

            $groupPriceList[$groupId]['min'] = $groupPriceList[$groupId]['max'] = 0.00;

            foreach ($options as $optionsValues) {
                $groupPriceList[$groupId]['min'] += min($optionsValues);
                $groupPriceList[$groupId]['max'] += max($optionsValues);
            }
        }

        return $groupPriceList;
    }
}
