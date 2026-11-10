<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterface;
use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
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

        return $this->minMaxPricesFactory->create([
            'min' => $min,
            'max' => $max,
            'minOriginal' => $original,
            'maxOriginal' => $originalMax,
        ]);
    }

    protected function addCustomerGroupsFinalPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        $min
    ) : PriceDataInterface
    {
        return $this->pricingStrategy->applyCustomerGroupsFinalPrices(
            $priceData,
            $pricingContext,
            $min,
            true
        );
    }
}
