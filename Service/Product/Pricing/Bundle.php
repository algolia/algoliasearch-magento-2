<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\Product\PriceDataFormatter;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Group;

class Bundle extends AbstractProductWithChildren
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
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

    /**
     * Override parent addAdditionalData function
     */
    protected function addAdditionalData(PriceDataInterface $priceData, PricingContextInterface $pricingContext)
    : PriceDataInterface
    {
        $data = $this->getMinMaxPrices($pricingContext);

        if ($data['min_price'] !== $data['max_price']) {
            $priceData = $this->handleBundleNonEqualMinMaxPrices($priceData, $data['min_price'], $data['max_price']);
        }

        if ($priceData->getPrice() === 0.00) {
            $priceData = $this->handleZeroDefaultPrice($priceData, $pricingContext, $data['min_price'], $data['max_price']);
        }

        if ($pricingContext->areCustomerGroupsEnabled()) {
            $priceData = $this->setFinalGroupPricesBundle($priceData, $data['min']);
        }

        return $priceData;
    }

    protected function getMinMaxPrices(PricingContextInterface $pricingContext): array
    {
        $product = $pricingContext->getProduct();

        $productWithPrice = $this->productRepository->getById($product->getId(), false, $product->getStoreId(), true);
        $productWithPrice->setData('website_id', $product->getStore()->getWebsiteId());
        $minPrice = $productWithPrice->getPriceInfo()->getPrice('final_price')->getMinimalPrice()->getValue();
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

        if ($pricingContext->isCurrencyDifferentFromBase()) {
            $minPrice = $this->pricingHelper->convertPrice(
                $minPrice,
                $pricingContext->getStore(),
                $pricingContext->getCurrencyCode(),
            );

            foreach ($minPriceArray as $groupId => $price) {
                $minPriceArray[$groupId] = $this->pricingHelper->convertPrice(
                    $price,
                    $pricingContext->getStore(),
                    $pricingContext->getCurrencyCode(),
                );

                if ($minPrice !== $max) {
                    $max = $this->pricingHelper->convertPrice(
                        $max,
                        $pricingContext->getStore(),
                        $pricingContext->getCurrencyCode(),
                    );
                }
            }
        }

        return [
            'min' => $minPriceArray,
            'max' => $maxPriceArray,
            'min_price' => $minPrice,
            'max_price' => $max
        ];
    }

    protected function handleBundleNonEqualMinMaxPrices(PriceDataInterface $priceData, $min, $max): PriceDataInterface
    {
        if ($min <= $priceData->getPrice()) {

            //// Do not keep special price that is already taken into account in min max
            $priceData->setSpecialFromDate("");
            $priceData->setSpecialToDate("");
            $priceData->setPrice(0); // will be reset just after
        }

        $priceData->setMaxPrice((float) $max);

        return $priceData;
    }

    protected function setFinalGroupPricesBundle(PriceDataInterface $priceData, $min): PriceDataInterface
    {
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $priceData->setPrice($min[$groupId], $groupId);
        }

        return $priceData;
    }
}
