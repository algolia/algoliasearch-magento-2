<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\Product\PriceDataFormatter;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupResourceCollection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

abstract class AbstractProduct
{
    protected CustomerGroupResourceCollection $groups;

    public function __construct(
        protected ConfigHelper $configHelper,
        protected PricingHelper $pricingHelper,
        protected DiagnosticsLogger $logger,
        protected PriceDataFormatter $priceDataFormatter
    ) {}

    /**
     * @throws DiagnosticsException
     * @throws LocalizedException
     */
    public function calculatePriceData(PriceDataInterface $priceData, PricingContextInterface $pricingContext)
    : PriceDataInterface
    {
        $this->logger->startProfiling(__METHOD__);

        $product = $pricingContext->getProduct();
        $this->groups = $this->pricingHelper->getCustomerGroupCollection();

        $this->filterCustomerGroups($pricingContext);

        $price = $product->getPrice();
        if ($this->configHelper->isFptEnabled($product->getStoreId())) {
            $price += $this->pricingHelper->getWeeeAmount($product);
        }
        if ($pricingContext->isCurrencyDifferentFromBase()) {
            $price = $this->pricingHelper->convertPrice(
                $price,
                $pricingContext->getStore(),
                $pricingContext->getCurrencyCode()
            );
        }

        $price = $this->pricingHelper->getTaxPrice($product, $price, $pricingContext->shouldIncludeTax());

        /**
         *  Basic inputs related to every product
         *
         *  [...
         *      'defaut' => X.XX,
         *      'special_from_date' => 1789984652,
         *      'special_to_date' => 1789984652
         *   ...
         *  ]
         */
        $priceData->setPrice($this->pricingHelper->round($price));
        $priceData->setSpecialFromDate(
            (!empty($product->getSpecialFromDate())) ? strtotime((string) $product->getSpecialFromDate()) : ''
        );
        $priceData->setSpecialToDate(
            (!empty($product->getSpecialToDate())) ? strtotime((string) $product->getSpecialToDate()) : ''
        );


        /**
         *  Additional inputs depending on multiple factors (customer groups, special/tier prices ...)
         */
        if ($pricingContext->areCustomerGroupsEnabled()) {
            $priceData = $this->addCustomerGroupsPrices($priceData, $pricingContext);
        }

        $priceData = $this->addSpecialPrices($priceData, $pricingContext);
        $priceData = $this->addTierPrices($priceData, $pricingContext);

        /**
         *  Additional inputs from child products
         */
        $priceData = $this->addAdditionalData($priceData, $pricingContext);

        $this->logger->stopProfiling(__METHOD__);

        return $this->priceDataFormatter->formatPriceDataObject($priceData, $pricingContext);
    }

    protected function filterCustomerGroups(PricingContextInterface $pricingContext): void
    {
        if (!$pricingContext->areCustomerGroupsEnabled()) {
            $this->groups->addFieldToFilter('main_table.customer_group_id', 0);
        } else {
            $excludedGroups = [];
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                $excludedWebsites = $this->pricingHelper->getCustomerGroupExcludedWebsites($groupId);
                if (in_array($pricingContext->getStore()->getWebsiteId(), $excludedWebsites)) {
                    $excludedGroups[] = $groupId;
                }
            }
            if(count($excludedGroups) > 0) {
                $this->groups->addFieldToFilter('main_table.customer_group_id', ['nin' => $excludedGroups]);
                $this->groups->clear();
            }
        }
    }

    protected function addAdditionalData(PriceDataInterface $priceData, PricingContextInterface $pricingContext)
    : PriceDataInterface
    {
        // Empty for products without children
        return $priceData;
    }

    protected function getSpecialPrice(PricingContextInterface $pricingContext): array
    {
        $product = $pricingContext->getProduct();
        $specialPrice = [];
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $specialPrices[$groupId] = [];
            $specialPrices[$groupId][] = $this->pricingHelper->getRulePrice($groupId, $product);
            // The price with applied catalog rules
            $finalPrice = $product->getFinalPrice(); // The product's special price
            if ($pricingContext->isFptEnabled()) {
                $finalPrice += $this->pricingHelper->getWeeeAmount($product);
            }
            $specialPrices[$groupId][] = $finalPrice;
            $specialPrices[$groupId] = array_filter($specialPrices[$groupId], fn($price) => $price > 0);
            $specialPrice[$groupId] = false;
            if ($specialPrices[$groupId] && $specialPrices[$groupId] !== []) {
                $specialPrice[$groupId] = min($specialPrices[$groupId]);
            }
            if ($specialPrice[$groupId]) {
                if ($pricingContext->isCurrencyDifferentFromBase()) {
                    $specialPrice[$groupId] =
                        $this->pricingHelper->round(
                            $this->pricingHelper->convertPrice(
                                $specialPrice[$groupId],
                                $pricingContext->getStore(),
                                $pricingContext->getCurrencyCode()
                            )
                        );
                }
                $specialPrice[$groupId] = $this->pricingHelper->getTaxPrice(
                    $product,
                    $specialPrice[$groupId],
                    $pricingContext->shouldIncludeTax()
                );
            }
        }

        return $specialPrice;
    }

    protected function getRulePrice($groupId, $product, $subProducts)
    {
        return $this->pricingHelper->getRulePrice($groupId, $product);
    }

    protected function getTierPrice(PricingContextInterface $pricingContext): array
    {
        $this->logger->startProfiling(__METHOD__);
        $product = $pricingContext->getProduct();
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

            if ($pricingContext->isCurrencyDifferentFromBase()) {
                $currentTierPrice =
                    $this->pricingHelper->round(
                        $this->pricingHelper->convertPrice(
                            $currentTierPrice,
                            $product->getStore(),
                            $pricingContext->getCurrencyCode()
                        )
                    );
            }
            $tierPrice[$groupId] = $this->pricingHelper->getTaxPrice(
                $product,
                $currentTierPrice,
                $pricingContext->shouldIncludeTax()
            );
        }

        $this->logger->stopProfiling(__METHOD__);

        return $tierPrice;
    }

    protected function addTierPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext
    ) : PriceDataInterface
    {
        $tierPrice = $this->getTierPrice($pricingContext);

        if ($pricingContext->areCustomerGroupsEnabled()) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');

                if ($tierPrice[$groupId]) {
                    $priceData->setTierPrice($tierPrice[$groupId], $groupId);
                }
            }
        }

        if ($tierPrice[0]) {
            $priceData->setTierPrice($this->pricingHelper->round($tierPrice[0]));
        }

        return $priceData;
    }

    protected function addCustomerGroupsPrices(PriceDataInterface $priceData, PricingContextInterface $pricingContext)
    : PriceDataInterface
    {
        $product = $pricingContext->getProduct();

        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $product->setData('customer_group_id', $groupId);
            $product->setData('website_id', $product->getStore()->getWebsiteId());
            $discountedPrice = $product->getPriceInfo()->getPrice('final_price')->getValue();
            if ($pricingContext->isCurrencyDifferentFromBase()) {
                $discountedPrice = $this->pricingHelper->convertPrice(
                    $discountedPrice,
                    $pricingContext->getStore(),
                    $pricingContext->getCurrencyCode()
                );
            }
            if ($discountedPrice !== false) {
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

    protected function addSpecialPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext
    ): PriceDataInterface
    {
        $specialPrice = $this->getSpecialPrice($pricingContext);

        if ($pricingContext->areCustomerGroupsEnabled()) {
            /** @var Group $group */
            foreach ($this->groups as $group) {
                $groupId = (int) $group->getData('customer_group_id');
                if ($specialPrice[$groupId]  && $specialPrice[$groupId] < $priceData->getPrice($groupId)) {
                    $priceData->setPrice($specialPrice[$groupId], $groupId);

                    if ($priceData->getPrice() > $priceData->getPrice($groupId)) {
                        $priceData->setOriginalPrice($priceData->getPrice(), $groupId);
                    }
                }
            }
        }

        if ($specialPrice[0] && $specialPrice[0] < $priceData->getPrice()) {
            $priceData->setOriginalPrice($priceData->getPrice());
            $priceData->setPrice($this->pricingHelper->round($specialPrice[0]));
        }

        return $priceData;
    }
}
