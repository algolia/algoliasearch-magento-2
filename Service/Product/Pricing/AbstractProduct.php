<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PriceDataInterfaceFactory;
use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupResourceCollection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

abstract class AbstractProduct
{
    protected Store $store;
    protected ?string $baseCurrencyCode;
    protected CustomerGroupResourceCollection $groups;
    protected bool $areCustomersGroupsEnabled;

    protected PriceDataInterface $priceData;

    public function __construct(
        protected ConfigHelper $configHelper,
        protected PricingHelper $pricingHelper,
        protected PriceDataInterfaceFactory $priceDataInterfaceFactory,
        protected DiagnosticsLogger $logger
    ) {}

    protected function initProductPricingConfiguration(Product $product): void
    {
        $this->store = $product->getStore();
        $this->areCustomersGroupsEnabled = $this->configHelper->isCustomerGroupsEnabled($product->getStoreId());
        $this->baseCurrencyCode = $this->store->getBaseCurrencyCode();
        $this->groups = $this->pricingHelper->getCustomerGroupCollection();

        $this->priceData = $this->priceDataInterfaceFactory->create();
    }

    /**
     * @throws DiagnosticsException
     * @throws LocalizedException
     */
    public function getPriceData(Product $product, $subProducts, string $currencyCode, bool $withTax): array
    {
        $this->logger->startProfiling(__METHOD__);
        $this->initProductPricingConfiguration($product);
        $this->filterCustomerGroups($product);

        $price = $product->getPrice();
        if ($this->configHelper->isFptEnabled($product->getStoreId())) {
            $price += $this->pricingHelper->getWeeeAmount($product);
        }
        if ($currencyCode !== $this->baseCurrencyCode) {
            $price = $this->pricingHelper->convertPrice($price, $this->store, $currencyCode);
        }

        $price = $this->pricingHelper->getTaxPrice($product, $price, $withTax);

        /**
         *  Basic inputs related to every product
         *
         *  [...
         *      'defaut' => X.XX,
         *      'default_formated' => "$XX.XX"
         *      'special_from_date' => 1789984652,
         *      'special_to_date' => 1789984652
         *   ...
         *  ]
         */
        $this->priceData->setPrice($this->pricingHelper->round($price));
        $this->priceData->setFormatedPrice(
            $this->pricingHelper->formatPrice($price, $this->store, $currencyCode)
        );
        $this->priceData->setSpecialFromDate(
            (!empty($product->getSpecialFromDate())) ? strtotime((string) $product->getSpecialFromDate()) : ''
        );
        $this->priceData->setSpecialToDate(
            (!empty($product->getSpecialToDate())) ? strtotime((string) $product->getSpecialToDate()) : ''
        );


        /**
         *  Additional inputs depending on multiple factors (customer groups, special/tier prices ...)
         */
        if ($this->areCustomersGroupsEnabled) {
            $this->addCustomerGroupsPrices($product, $currencyCode, $withTax);
        }

        $specialPrice = $this->getSpecialPrice($product, $currencyCode, $withTax, $subProducts);
        $this->addSpecialPrices($specialPrice, $currencyCode);

        $tierPrice = $this->getTierPrice($product, $currencyCode, $withTax);
        $this->addTierPrices($tierPrice, $currencyCode);

        /**
         *  Additional inputs from child products
         */
        $this->addAdditionalData($product, $withTax, $subProducts, $currencyCode);

        $this->logger->stopProfiling(__METHOD__);

        return $this->priceData->getData();
    }

    protected function filterCustomerGroups(Product $product): void
    {
        if (!$this->areCustomersGroupsEnabled) {
            $this->groups->addFieldToFilter('main_table.customer_group_id', 0);
        } else {
            $excludedGroups = [];
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                $excludedWebsites = $this->pricingHelper->getCustomerGroupExcludedWebsites($groupId);
                if (in_array($product->getStore()->getWebsiteId(), $excludedWebsites)) {
                    $excludedGroups[] = $groupId;
                }
            }
            if(count($excludedGroups) > 0) {
                $this->groups->addFieldToFilter('main_table.customer_group_id', ['nin' => $excludedGroups]);
                $this->groups->clear();
            }
        }
    }

    protected function addAdditionalData($product, $withTax, $subProducts, $currencyCode): void
    {
        // Empty for products without children
    }

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

    protected function getRulePrice($groupId, $product, $subProducts)
    {
        return $this->pricingHelper->getRulePrice($groupId, $product);
    }

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
                    $this->pricingHelper->round(
                        $this->pricingHelper->convertPrice($currentTierPrice, $product->getStore(), $currencyCode)
                    );
            }
            $tierPrice[$groupId] = $this->pricingHelper->getTaxPrice($product, $currentTierPrice, $withTax);
        }

        $this->logger->stopProfiling(__METHOD__);

        return $tierPrice;
    }

    protected function addTierPrices($tierPrice, $currencyCode): void
    {
        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');

                if ($tierPrice[$groupId]) {
                    $this->priceData->setTierPrice($tierPrice[$groupId], $groupId);
                    $this->priceData->setFormatedTierPrice(
                        $this->pricingHelper->formatPrice($tierPrice[$groupId], $this->store, $currencyCode),
                        $groupId
                    );
                }
            }
        }

        if ($tierPrice[0]) {
            $this->priceData->setTierPrice($this->pricingHelper->round($tierPrice[0]));
            $this->priceData->setFormatedTierPrice(
                $this->pricingHelper->formatPrice($tierPrice[0], $this->store, $currencyCode),
            );
        }
    }

    protected function addCustomerGroupsPrices(Product $product, $currencyCode, $withTax): void
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
                $this->priceData->setPrice(
                    $this->pricingHelper->getTaxPrice($product, $discountedPrice, $withTax),
                    $groupId
                );
                $this->priceData->setFormatedPrice(
                    $this->pricingHelper->formatPrice(
                        $this->priceData->getPrice($groupId), $this->store, $currencyCode
                    ),
                    $groupId
                );

                if ($this->priceData->getPrice() > $this->priceData->getPrice($groupId)) {
                    $this->priceData->setFormatedOriginalPrice(
                        $this->priceData->getFormatedPrice(),
                        $groupId
                    );
                }
            } else {
                $this->priceData->setPrice($this->priceData->getPrice(), $groupId);
                $this->priceData->setFormatedPrice($this->priceData->getFormatedPrice(), $groupId);
            }
        }

        $product->setData('customer_group_id', null);
    }

    protected function addSpecialPrices($specialPrice, $currencyCode): void
    {
        if ($this->areCustomersGroupsEnabled) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                if ($specialPrice[$groupId]  && $specialPrice[$groupId] < $this->priceData->getPrice($groupId)) {
                    $this->priceData->setPrice($specialPrice[$groupId], $groupId);
                    $this->priceData->setFormatedPrice(
                        $this->pricingHelper->formatPrice($specialPrice[$groupId], $this->store, $currencyCode),
                        $groupId
                    );

                    if ($this->priceData->getPrice() > $this->priceData->getPrice($groupId)) {
                        $this->priceData->setFormatedOriginalPrice($this->priceData->getFormatedPrice(), $groupId);
                    }
                }
            }
        }

        if ($specialPrice[0] && $specialPrice[0] < $this->priceData->getPrice()) {
            $this->priceData->setFormatedOriginalPrice($this->priceData->getFormatedPrice());
            $this->priceData->setPrice($this->pricingHelper->round($specialPrice[0]));
            $this->priceData->setFormatedPrice(
                $this->pricingHelper->formatPrice($specialPrice[0], $this->store, $currencyCode)
            );
        }
    }
}
