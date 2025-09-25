<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Model\Group;
use Magento\Framework\Exception\LocalizedException;

abstract class ProductWithoutChildren
{
    protected $store;
    protected $baseCurrencyCode;
    protected $groups;
    protected $areCustomersGroupsEnabled;

    public function __construct(
        protected ConfigHelper $configHelper,
        protected PricingHelper $pricingHelper,
        protected DiagnosticsLogger $logger
    ) {}

    /**
     * @param Product $product
     * @param $subProducts
     * @param bool $withTax
     * @return array
     * @throws DiagnosticsException
     * @throws LocalizedException
     */
    public function getPriceData(Product $product, $subProducts, bool $withTax): array
    {
        $priceData = [];
        $this->logger->startProfiling(__METHOD__);
        $this->store = $product->getStore();
        $this->areCustomersGroupsEnabled = $this->configHelper->isCustomerGroupsEnabled($product->getStoreId());
        $currencies = $this->store->getAvailableCurrencyCodes(true);
        $this->baseCurrencyCode = $this->store->getBaseCurrencyCode();
        $this->groups = $this->pricingHelper->getCustomerGroupCollection();

        if (!$this->areCustomersGroupsEnabled) {
            $this->groups->addFieldToFilter('main_table.customer_group_id', 0);
        } else {
            $excludedGroups = [];
            foreach ($this->groups as $group) {
                $groupId = (int)$group->getData('customer_group_id');
                $excludedWebsites = $this->pricingHelper->getCustomerGroupExcludedWebsites($groupId);
                if (in_array($product->getStore()->getWebsiteId(), $excludedWebsites)) {
                    $excludedGroups[] = $groupId;
                }
            }
            if(count($excludedGroups) > 0) {
                $this->groups->addFieldToFilter('main_table.customer_group_id', ["nin" => $excludedGroups]);
                $this->groups->clear();
            }
        }

        $product->setPriceCalculation(true);
        foreach ($currencies as $currencyCode) {
            $priceData[$currencyCode] = [];
            $price = $product->getPrice();
            if ($this->configHelper->isFptEnabled($product->getStoreId())) {
                $price += $this->pricingHelper->getWeeeAmount($product);
            }
            if ($currencyCode !== $this->baseCurrencyCode) {
                $price = $this->pricingHelper->convertPrice($price, $this->store, $currencyCode);
            }

            $price = $this->pricingHelper->getTaxPrice($product, $price, $withTax);
            $priceData[$currencyCode]['default'] = $this->pricingHelper->round($price);
            $priceData[$currencyCode]['default_formated'] =
                $this->pricingHelper->formatPrice($price, $this->store, $currencyCode);

            $specialPrice = $this->getSpecialPrice($product, $currencyCode, $withTax, $subProducts);
            $tierPrice = $this->getTierPrice($product, $currencyCode, $withTax);

            if ($this->areCustomersGroupsEnabled) {
                $priceData = $this->addCustomerGroupsPrices($priceData, $product, $currencyCode, $withTax);
            }

            $priceData[$currencyCode]['special_from_date'] =
                (!empty($product->getSpecialFromDate())) ? strtotime((string) $product->getSpecialFromDate()) : '';
            $priceData[$currencyCode]['special_to_date'] =
                (!empty($product->getSpecialToDate())) ? strtotime((string) $product->getSpecialToDate()) : '';

            $priceData = $this->addSpecialPrices($priceData, $specialPrice, $currencyCode);
            $priceData = $this->addTierPrices($priceData, $tierPrice, $currencyCode);
            $priceData = $this->addAdditionalData($priceData, $product, $withTax, $subProducts, $currencyCode);
        }


        $this->logger->stopProfiling(__METHOD__);

        return $priceData;
    }

    /**
     * @param $priceData
     * @param $product
     * @param $withTax
     * @param $subProducts
     * @param $currencyCode
     * @return array
     */
    protected function addAdditionalData($priceData, $product, $withTax, $subProducts, $currencyCode): array
    {
        // Empty for products without children
        return $priceData;
    }

    /**
     * @param Product $product
     * @param $currencyCode
     * @param $withTax
     * @param $subProducts
     * @return array
     */
    protected function getSpecialPrice(Product $product, $currencyCode, $withTax, $subProducts): array
    {
        $specialPrice = [];
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $specialPrices[$groupId] = [];
            $specialPrices[$groupId][] = $this->pricingHelper->getRulePrice($groupId, $product);
            // The price with applied catalog rules
            $finalPrice = $product->getFinalPrice(); // The product's special price
            if ($this->configHelper->isFptEnabled($product->getStoreId())) {
                $finalPrice += $this->pricingHelper->getWeeeAmount($product);
            }
            $specialPrices[$groupId][] = $finalPrice;
            $specialPrices[$groupId] = array_filter($specialPrices[$groupId], fn($price) => $price > 0);
            $specialPrice[$groupId] = false;
            if ($specialPrices[$groupId] && $specialPrices[$groupId] !== []) {
                $specialPrice[$groupId] = min($specialPrices[$groupId]);
            }
            if ($specialPrice[$groupId]) {
                if ($currencyCode !== $this->baseCurrencyCode) {
                    $specialPrice[$groupId] =
                        $this->pricingHelper->round(
                            $this->pricingHelper->convertPrice($specialPrice[$groupId], $this->store, $currencyCode)
                        );
                }
                $specialPrice[$groupId] = $this->pricingHelper->getTaxPrice($product, $specialPrice[$groupId], $withTax);
            }
        }
        return $specialPrice;
    }

    /**
     * @param $groupId
     * @param $product
     * @param $subProducts
     * @return float|int|mixed
     */
    protected function getRulePrice($groupId, $product, $subProducts)
    {
        return $this->pricingHelper->getRulePrice($groupId, $product);
    }

    /**
     * @param Product $product
     * @param $currencyCode
     * @param $withTax
     * @return array
     */
    protected function getTierPrice(Product $product, $currencyCode, $withTax)
    {
        $this->logger->startProfiling(__METHOD__);
        $tierPrice = [];
        $tierPrices = [];

        if (!empty($product->getTierPrices())) {
            $product->setData('website_id', $product->getStore()->getWebsiteId());
            $productTierPrices = $product->getTierPrices();
            foreach ($productTierPrices as $productTierPrice) {
                if (!isset($tierPrices[$productTierPrice->getCustomerGroupId()])) {
                    $tierPrices[$productTierPrice->getCustomerGroupId()] = $productTierPrice->getValue();

                    continue;
                }

                $tierPrices[$productTierPrice->getCustomerGroupId()] = min(
                    $tierPrices[$productTierPrice->getCustomerGroupId()],
                    $productTierPrice->getValue()
                );
            }
        }

        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $tierPrice[$groupId] = false;

            $currentTierPrice = null;
            if (!isset($tierPrices[$groupId]) && !isset($tierPrices[GroupInterface::CUST_GROUP_ALL])) {
                continue;
            }

            if (isset($tierPrices[GroupInterface::CUST_GROUP_ALL])
                && $tierPrices[GroupInterface::CUST_GROUP_ALL] !== []) {
                $currentTierPrice = $tierPrices[GroupInterface::CUST_GROUP_ALL];
            }

            if (isset($tierPrices[$groupId]) && $tierPrices[$groupId] !== []) {
                $currentTierPrice = $currentTierPrice === null ?
                    $tierPrices[$groupId] :
                    min($currentTierPrice, $tierPrices[$groupId]);
            }

            if ($currencyCode !== $this->baseCurrencyCode) {
                $currentTierPrice =
                    $this->pricingHelper->round($this->pricingHelper->convertPrice($currentTierPrice, $currencyCode));
            }
            $tierPrice[$groupId] = $this->pricingHelper->getTaxPrice($product, $currentTierPrice, $withTax);
        }

        $this->logger->stopProfiling(__METHOD__);

        return $tierPrice;
    }

    /**
     * @param array $priceData
     * @param $tierPrice
     * @param $currencyCode
     * @return array
     */
    protected function addTierPrices(array $priceData, $tierPrice, $currencyCode): array
    {
        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');

                if ($tierPrice[$groupId]) {
                    $priceData[$currencyCode]['group_' . $groupId . '_tier'] = $tierPrice[$groupId];

                    $priceData[$currencyCode]['group_' . $groupId . '_tier_formated'] =
                        $this->pricingHelper->formatPrice($tierPrice[$groupId], $this->store, $currencyCode);
                }
            }

            return $priceData;
        }

        if ($tierPrice[0]) {
            $priceData[$currencyCode]['default_tier'] = $this->pricingHelper->round($tierPrice[0]);
            $priceData[$currencyCode]['default_tier_formated'] =
                $this->pricingHelper->formatPrice($tierPrice[0], $this->store, $currencyCode);
        }

        return $priceData;
    }

    /**
     * @param array $priceData
     * @param Product $product
     * @param $currencyCode
     * @param $withTax
     * @return array
     */
    protected function addCustomerGroupsPrices(array $priceData, Product $product, $currencyCode, $withTax): array
    {
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $product->setData('customer_group_id', $groupId);
            $product->setData('website_id', $product->getStore()->getWebsiteId());
            $discountedPrice = $product->getPriceInfo()->getPrice('final_price')->getValue();
            if ($currencyCode !== $this->baseCurrencyCode) {
                $discountedPrice = $this->pricingHelper->convertPrice($discountedPrice, $this->store, $currencyCode);
            }
            if ($discountedPrice !== false) {
                $priceData[$currencyCode]['group_' . $groupId] =
                    $this->pricingHelper->getTaxPrice($product, $discountedPrice, $withTax);
                $priceData[$currencyCode]['group_' . $groupId . '_formated'] =
                    $this->pricingHelper->formatPrice(
                        $priceData[$currencyCode]['group_' . $groupId],
                        $this->store,
                        $currencyCode
                    );
                if ($priceData[$currencyCode]['default'] > $priceData[$currencyCode]['group_' . $groupId]) {
                    $priceData[$currencyCode]['group_' . $groupId . '_original_formated'] =
                        $priceData[$currencyCode]['default_formated'];
                }
            } else {
                $priceData[$currencyCode]['group_' . $groupId] = $priceData[$currencyCode]['default'];
                $priceData[$currencyCode]['group_' . $groupId . '_formated'] =
                    $priceData[$currencyCode]['default_formated'];
            }
        }

        $product->setData('customer_group_id', null);

        return $priceData;
    }

    /**
     * @param $priceData
     * @param $specialPrice
     * @param $currencyCode
     * @return array
     */
    protected function addSpecialPrices($priceData, $specialPrice, $currencyCode): array
    {
        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                if ($specialPrice[$groupId]
                    && $specialPrice[$groupId] < $priceData[$currencyCode]['group_' . $groupId]) {
                    $priceData['group_' . $groupId] = $specialPrice[$groupId];
                    $priceData['group_' . $groupId . '_formated'] =
                        $this->pricingHelper->formatPrice($specialPrice[$groupId], $this->store, $currencyCode);
                    if ($priceData[$currencyCode]['default'] >
                        $priceData[$currencyCode]['group_' . $groupId]) {
                        $priceData[$currencyCode]['group_' . $groupId . '_original_formated'] =
                            $priceData[$currencyCode]['default_formated'];
                    }
                }
            }
            return $priceData;
        }

        if ($specialPrice[0] && $specialPrice[0] < $priceData[$currencyCode]['default']) {
            $priceData[$currencyCode]['default_original_formated'] =
                $priceData[$currencyCode]['default_formated'];
            $priceData[$currencyCode]['default'] = $this->pricingHelper->round($specialPrice[0]);
            $priceData[$currencyCode]['default_formated'] =
                $this->pricingHelper->formatPrice($specialPrice[0], $this->store, $currencyCode);
        }

        return $priceData;
    }
}
