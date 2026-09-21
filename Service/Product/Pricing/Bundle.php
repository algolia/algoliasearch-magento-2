<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Customer\Model\Group;

class Bundle extends ProductWithChildren
{
    public function __construct(
        protected ProductFactory $productFactory,
        protected ConfigHelper $configHelper,
        protected PricingHelper $pricingHelper,
        protected DiagnosticsLogger $logger
    ) {
        parent::__construct(
            $configHelper,
            $pricingHelper,
            $logger
        );
    }

    /**
     * Override parent addAdditionalData function
     * @param $priceData
     * @param $product
     * @param $withTax
     * @param $subProducts
     * @param $currencyCode
     * @return array
     */
    protected function addAdditionalData($priceData, $product, $withTax, $subProducts, $currencyCode): array
    {
        $data = $this->getMinMaxPrices($product, $withTax, $subProducts, $currencyCode);
        $dashedFormat = $this->pricingHelper->formatDashedPriceFormat(
            $data['min_price'],
            $data['max_price'],
            $this->store,
            $currencyCode
        );

        if ($data['min_price'] !== $data['max_price']) {
            $priceData = $this->handleBundleNonEqualMinMaxPrices($priceData, $currencyCode, $data['min_price'], $data['max_price'], $dashedFormat);
        }

        $priceData = $this->handleOriginalPrice(
            $priceData,
            $currencyCode,
            $data['min_price'],
            $data['max_price'],
            $data['min_original'],
            $data['max_original']
        );

        if (!$priceData[$currencyCode]['default']) {
            $priceData = $this->handleZeroDefaultPrice($priceData, $currencyCode, $data['min_price'], $data['max_price']);
        }

        if ($this->areCustomersGroupsEnabled) {
            $groupedDashedFormat = $this->getBundleDashedPriceFormat($data['min'], $data['max'], $currencyCode);
            $priceData = $this->setFinalGroupPricesBundle(
                $priceData,
                $currencyCode,
                $data['min'],
                $data['max'],
                $groupedDashedFormat
            );
        }

        return $priceData;
    }

    /**
     * @param Product $product
     * @param $withTax
     * @param $subProducts
     * @param $currencyCode
     * @return array
     */
    protected function getMinMaxPrices(Product $product, $withTax, $subProducts, $currencyCode): array
    {
        $productWithPrice = $this->productFactory->create()->load($product->getId());
        $productWithPrice->setData('website_id', $product->getStore()->getWebsiteId());
        $minPrice = $productWithPrice->getPriceInfo()->getPrice('final_price')->getMinimalPrice()->getValue();
        $minOriginalPrice = $productWithPrice->getPriceInfo()->getPrice('regular_price')->getMinimalPrice()->getValue();
        $maxOriginalPrice = $productWithPrice->getPriceInfo()->getPrice('regular_price')->getMaximalPrice()->getValue();
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
            $minOriginalPrice = $this->pricingHelper->convertPrice($minOriginalPrice, $this->store, $currencyCode);
            $maxOriginalPrice = $this->pricingHelper->convertPrice($maxOriginalPrice, $this->store, $currencyCode);
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
            'max_price' => $max,
            'min_original' => $minOriginalPrice,
            'max_original' => $maxOriginalPrice
        ];
    }

    /**
     * @param $priceData
     * @param $currencyCode
     * @param $min
     * @param $max
     * @param $dashedFormat
     * @return array
     */
    protected function handleBundleNonEqualMinMaxPrices($priceData, $currencyCode, $min, $max, $dashedFormat): array
    {
        if (isset($priceData[$currencyCode]['default_original_formated']) === false
            || $min <= $priceData[$currencyCode]['default']) {
            $priceData[$currencyCode]['default_formated'] = $dashedFormat;
            //// Do not keep special price that is already taken into account in min max
            unset(
                $priceData[$currencyCode]['special_from_date'],
                $priceData[$currencyCode]['special_to_date'],
                $priceData[$currencyCode]['default_original_formated']
            );
            $priceData[$currencyCode]['default'] = 0; // will be reset just after
        }

        $priceData[$currencyCode]['default_max'] = $max;

        return $priceData;
    }

    /**
     * @param $minPrices
     * @param $max
     * @param $currencyCode
     * @return array
     */
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

    /**
     * @param $priceData
     * @param $currencyCode
     * @param $min
     * @param $max
     * @param $dashedFormat
     * @return array
     */
    protected function setFinalGroupPricesBundle($priceData, $currencyCode, $min, $max, $dashedFormat): array
    {
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $priceData[$currencyCode]['group_' . $groupId] = $min[$groupId];
            if ($min[$groupId] === $max[$groupId]) {
                $priceData[$currencyCode]['group_' . $groupId . '_formated'] =
                    $priceData[$currencyCode]['default_formated'];
            } else {
                $priceData[$currencyCode]['group_' . $groupId . '_formated'] = $dashedFormat[$groupId];
            }
        }

        return $priceData;
    }
}
