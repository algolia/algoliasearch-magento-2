<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\Product\PriceDataFormatter;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
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
    protected function addAdditionalData($product, $withTax, $subProducts, $currencyCode): void
    {
        $data = $this->getMinMaxPrices($product, $withTax, $subProducts, $currencyCode);

        if ($data['min_price'] !== $data['max_price']) {
            $this->handleBundleNonEqualMinMaxPrices($data['min_price'], $data['max_price']);
        }

        if ($this->priceData->getPrice() === 0.00) {
            $this->handleZeroDefaultPrice($currencyCode, $data['min_price'], $data['max_price']);
        }

        if ($this->areCustomersGroupsEnabled) {
            $this->setFinalGroupPricesBundle($data['min']);
        }
    }

    protected function getMinMaxPrices(Product $product, $withTax, $subProducts, $currencyCode): array
    {
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

        if ($currencyCode !== $this->baseCurrencyCode) {
            $minPrice = $this->pricingHelper->convertPrice($minPrice, $this->store, $currencyCode);
            foreach ($minPriceArray as $groupId => $price) {
                $minPriceArray[$groupId] = $this->pricingHelper->convertPrice($price, $this->store, $currencyCode);
            }
            if ($minPrice !== $max) {
                $max = $this->pricingHelper->convertPrice($max, $this->store, $currencyCode);
            }
        }

        return [
            'min' => $minPriceArray,
            'max' => $maxPriceArray,
            'min_price' => $minPrice,
            'max_price' => $max
        ];
    }

    protected function handleBundleNonEqualMinMaxPrices($min, $max): void
    {
        if ($min <= $this->priceData->getPrice()) {

            //// Do not keep special price that is already taken into account in min max
            $this->priceData->setSpecialFromDate("");
            $this->priceData->setSpecialToDate("");
            $this->priceData->setPrice(0); // will be reset just after
        }

        $this->priceData->setMaxPrice((float) $max);
    }

    protected function getBundleDashedPriceFormat($minPrices, $max, $currencyCode) : array
    {
        $dashedFormatPrice = [];
        foreach ($minPrices as $groupId => $min) {
            if ($min === $max[$groupId]) {
                $dashedFormatPrice [$groupId] =  '';
            }
            $dashedFormatPrice[$groupId] =
                $this->pricingHelper->formatPrice($min, $this->store, $currencyCode) .
                ' - ' . $this->pricingHelper->formatPrice($max[$groupId], $this->store, $currencyCode);
        }

        return $dashedFormatPrice;
    }

    protected function setFinalGroupPricesBundle($min): void
    {
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $this->priceData->setPrice($min[$groupId], $groupId);
        }
    }
}
