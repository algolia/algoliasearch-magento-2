<?php

namespace Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\CatalogRule\Model\ResourceModel\Rule;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Config as TaxConfig;

abstract class ProductWithoutChildren
{
    protected $configHelper;
    protected $customerGroupCollectionFactory;
    protected $priceCurrency;
    protected $catalogHelper;
    protected $taxHelper;
    protected $rule;

    protected $store;
    protected $baseCurrencyCode;
    protected $groups;
    protected $areCustomersGroupsEnabled;
    protected $customData = [];

    public function __construct(
        ConfigHelper $configHelper,
        CollectionFactory $customerGroupCollectionFactory,
        PriceCurrencyInterface $priceCurrency,
        CatalogHelper $catalogHelper,
        TaxHelper $taxHelper,
        Rule $rule
    ) {
        $this->configHelper = $configHelper;
        $this->customerGroupCollectionFactory = $customerGroupCollectionFactory;
        $this->priceCurrency = $priceCurrency;
        $this->catalogHelper = $catalogHelper;
        $this->taxHelper = $taxHelper;
        $this->rule = $rule;
    }

    public function addPriceData($customData, Product $product, $subProducts)
    {
        $this->customData = $customData;
        $this->store = $product->getStore();
        $this->areCustomersGroupsEnabled = $this->configHelper->isCustomerGroupsEnabled($product->getStoreId());
        $currencies = $this->store->getAvailableCurrencyCodes();
        $this->baseCurrencyCode = $this->store->getBaseCurrencyCode();
        $this->groups = $this->customerGroupCollectionFactory->create();
        $fields = $this->getFields();

        if (!$this->areCustomersGroupsEnabled) {
            $this->groups->addFieldToFilter('main_table.customer_group_id', 0);
        }

        // price/price_with_tax => true/false
        foreach ($fields as $field => $withTax) {
            $this->customData[$field] = [];

            foreach ($currencies as $currencyCode) {
                $this->customData[$field][$currencyCode] = [];

                $price = $product->getPrice();
                if ($currencyCode !== $this->baseCurrencyCode) {
                    $price = $this->convertPrice($price, $currencyCode);
                }

                $price = $this->getTaxPrice($product, $price, $withTax);

                $this->customData[$field][$currencyCode]['default'] = $this->priceCurrency->round($price);
                $this->customData[$field][$currencyCode]['default_formated'] = $this->formatPrice($price, $currencyCode);

                $specialPrice = $this->getSpecialPrice($product, $currencyCode, $withTax);
                $tierPrice = $this->getTierPrice($product, $currencyCode, $withTax);

                if ($this->areCustomersGroupsEnabled) {
                    $this->addCustomerGroupsPrices($product, $currencyCode, $withTax, $field);
                }

                $this->customData[$field][$currencyCode]['special_from_date'] =
                    strtotime($product->getSpecialFromDate());
                $this->customData[$field][$currencyCode]['special_to_date'] =
                    strtotime($product->getSpecialToDate());

                $this->addSpecialPrices($specialPrice, $field, $currencyCode);
                $this->addTierPrices($tierPrice, $field, $currencyCode);

                $this->addAdditionalData($product, $withTax, $subProducts, $currencyCode, $field);
            }
        }

        return $this->customData;
    }

    protected function getFields()
    {
        $priceDisplayType = $this->taxHelper->getPriceDisplayType($this->store);

        if ($priceDisplayType === TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX) {
            return ['price' => false];
        }

        if ($priceDisplayType === TaxConfig::DISPLAY_TYPE_INCLUDING_TAX) {
            return ['price' => true];
        }

        return ['price' => false, 'price_with_tax' => true];
    }

    protected function addAdditionalData($product, $withTax, $subProducts, $currencyCode, $field)
    {
        // Empty for products without children
    }

    protected function formatPrice($amount, $currencyCode)
    {
        $currency = $this->priceCurrency->getCurrency($this->store, $currencyCode);
        $options = ['locale' => $this->configHelper->getStoreLocale($this->store->getId())];

        return $currency->formatPrecision($amount, PriceCurrencyInterface::DEFAULT_PRECISION, $options, false);
    }

    protected function convertPrice($amount, $currencyCode)
    {
        return $this->priceCurrency->convert($amount, $this->store, $currencyCode);
    }

    protected function getTaxPrice($product, $amount, $withTax)
    {
        return (float) $this->catalogHelper->getTaxPrice(
            $product,
            $amount,
            $withTax,
            null,
            null,
            null,
            $this->store,
            null
        );
    }

    protected function getSpecialPrice(Product $product, $currencyCode, $withTax)
    {
        $specialPrice = [];

        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $specialPrices[$groupId] = [];
            $specialPrices[$groupId][] = $this->getRulePrice($groupId, $product);

            // The price with applied catalog rules
            $specialPrices[$groupId][] = $product->getFinalPrice(); // The product's special price

            $specialPrices[$groupId] = array_filter($specialPrices[$groupId], function ($price) {
                return $price > 0;
            });

            $specialPrice[$groupId] = false;
            if ($specialPrices[$groupId] && $specialPrices[$groupId] !== []) {
                $specialPrice[$groupId] = min($specialPrices[$groupId]);
            }

            if ($specialPrice[$groupId]) {
                if ($currencyCode !== $this->baseCurrencyCode) {
                    $specialPrice[$groupId] =
                        $this->priceCurrency->round($this->convertPrice($specialPrice[$groupId], $currencyCode));
                }

                $specialPrice[$groupId] = $this->getTaxPrice($product, $specialPrice[$groupId], $withTax);
            }
        }

        return $specialPrice;
    }

    protected function getTierPrice(Product $product, $currencyCode, $withTax)
    {
        $tierPrice = [];
        $tierPrices = [];

        if (!is_null($product->getTierPrices())) {
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
                $currentTierPrice = min($currentTierPrice, $tierPrices[$groupId]);
            }

            if ($currencyCode !== $this->baseCurrencyCode) {
                $tierPrices[$groupId] =
                    $this->priceCurrency->round($this->convertPrice($currentTierPrice, $currencyCode));
            }
            $tierPrice[$groupId] = $this->getTaxPrice($product, $currentTierPrice, $withTax);
        }

        return $tierPrice;
    }

    protected function getRulePrice($groupId, $product)
    {
        return (float) $this->rule->getRulePrice(
            new \DateTime(),
            $this->store->getWebsiteId(),
            $groupId,
            $product->getId()
        );
    }

    protected function addCustomerGroupsPrices(Product $product, $currencyCode, $withTax, $field)
    {
        /** @var \Magento\Customer\Model\Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');

            $product->setData('customer_group_id', $groupId);

            $discountedPrice = $product->getPriceModel()->getFinalPrice(1, $product);
            if ($currencyCode !== $this->baseCurrencyCode) {
                $discountedPrice = $this->convertPrice($discountedPrice, $currencyCode);
            }

            if ($discountedPrice !== false) {
                $this->customData[$field][$currencyCode]['group_' . $groupId] =
                    $this->getTaxPrice($product, $discountedPrice, $withTax);

                $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] =
                    $this->formatPrice(
                        $this->customData[$field][$currencyCode]['group_' . $groupId],
                        $currencyCode
                    );

                if ($this->customData[$field][$currencyCode]['default'] >
                    $this->customData[$field][$currencyCode]['group_' . $groupId]) {
                    $this->customData[$field][$currencyCode]['group_' . $groupId . '_original_formated'] =
                        $this->customData[$field][$currencyCode]['default_formated'];
                }
            } else {
                $this->customData[$field][$currencyCode]['group_' . $groupId] =
                    $this->customData[$field][$currencyCode]['default'];

                $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] =
                    $this->customData[$field][$currencyCode]['default_formated'];
            }
        }

        $product->setData('customer_group_id', null);
    }

    protected function addSpecialPrices($specialPrice, $field, $currencyCode)
    {
        if ($this->areCustomersGroupsEnabled) {
            /** @var \Magento\Customer\Model\Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');

                if ($specialPrice[$groupId]
                    && $specialPrice[$groupId] < $this->customData[$field][$currencyCode]['group_' . $groupId]) {
                    $this->customData[$field][$currencyCode]['group_' . $groupId] = $specialPrice[$groupId];

                    $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] =
                        $this->formatPrice($specialPrice[$groupId], $currencyCode);

                    if ($this->customData[$field][$currencyCode]['default'] >
                        $this->customData[$field][$currencyCode]['group_' . $groupId]) {
                        $this->customData[$field][$currencyCode]['group_' . $groupId . '_original_formated'] =
                            $this->customData[$field][$currencyCode]['default_formated'];
                    }
                }
            }

            return;
        }

        if ($specialPrice[0] && $specialPrice[0] < $this->customData[$field][$currencyCode]['default']) {
            $this->customData[$field][$currencyCode]['default_original_formated'] =
                $this->customData[$field][$currencyCode]['default_formated'];

            $this->customData[$field][$currencyCode]['default'] = $this->priceCurrency->round($specialPrice[0]);
            $this->customData[$field][$currencyCode]['default_formated'] =
                $this->formatPrice($specialPrice[0], $currencyCode);
        }
    }

    protected function addTierPrices($tierPrice, $field, $currencyCode)
    {
        if ($this->areCustomersGroupsEnabled) {
            /** @var \Magento\Customer\Model\Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');

                if ($tierPrice[$groupId]) {
                    $this->customData[$field][$currencyCode]['group_' . $groupId . '_tier'] = $tierPrice[$groupId];

                    $this->customData[$field][$currencyCode]['group_' . $groupId . '_tier_formated'] =
                        $this->formatPrice($tierPrice[$groupId], $currencyCode);
                }
            }

            return;
        }

        if ($tierPrice[0]) {
            $this->customData[$field][$currencyCode]['default_tier'] = $this->priceCurrency->round($tierPrice[0]);
            $this->customData[$field][$currencyCode]['default_tier_formated'] =
                $this->formatPrice($tierPrice[0], $currencyCode);
        }
    }
}
