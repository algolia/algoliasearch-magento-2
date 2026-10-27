<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterface;
use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterfaceFactory;
use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\Product\CatalogRulesManager;
use Algolia\AlgoliaSearch\Service\Product\PriceDataFormatter;
use Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups\CustomerGroupsManager;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\CustomerGroupPricingStrategy;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\DefaultPricingStrategy;
use Magento\Catalog\Model\Product;

abstract class AbstractProductWithChildren extends AbstractProduct
{
    public function __construct(
        protected MinMaxPricesInterfaceFactory $minMaxPricesFactory,
        protected ConfigHelper $configHelper,
        protected PricingHelper $pricingHelper,
        protected DiagnosticsLogger $logger,
        protected PriceDataFormatter $priceDataFormatter,
        protected CatalogRulesManager $catalogRulesManager,
        protected CustomerGroupsManager $customerGroupsManager,
        protected DefaultPricingStrategy $defaultPricingStrategy,
        protected CustomerGroupPricingStrategy $customerGroupPricingStrategy
    ) {
        parent::__construct(
            $configHelper,
            $pricingHelper,
            $logger,
            $priceDataFormatter,
            $catalogRulesManager,
            $customerGroupsManager,
            $defaultPricingStrategy,
            $customerGroupPricingStrategy
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
        $finalPrice = $this->pricingHelper->handleConversion($finalPrice, $pricingContext);
        $basePrice  = $subProduct->getPrice();
        $basePrice = $this->pricingHelper->handleConversion($basePrice, $pricingContext);

        $price = $minPrice ?? $this->pricingHelper->getTaxPrice($product, $finalPrice, $pricingContext->shouldIncludeTax(), false);
        $basePrice = $this->pricingHelper->getTaxPrice($product, $basePrice, $pricingContext->shouldIncludeTax(), false);

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
        return $this->pricingStrategy->applyPriceRange($priceData, $pricingContext, $minMaxPrices);
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

        $priceData = $this->pricingStrategy->applyChildrenCustomerGroupPrices($priceData, $pricingContext, $minMaxPrices);
        $min = $minMaxPrices->getMin();
        $priceData = $this->addCustomerGroupsFinalPrices($priceData, $pricingContext, $min);

        return $priceData;
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
            $min
        );
    }
}
