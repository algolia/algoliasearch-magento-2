<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Api\Product\PricingStrategyInterface;
use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\Product\CatalogRulesManager;
use Algolia\AlgoliaSearch\Service\Product\PriceDataFormatter;
use Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups\CustomerGroupsManager;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\CustomerGroupPricingStrategy;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\DefaultPricingStrategy;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Model\Group;
use Magento\Framework\Exception\LocalizedException;

abstract class AbstractProduct
{
    protected PricingStrategyInterface $pricingStrategy;

    public function __construct(
        protected ConfigHelper $configHelper,
        protected PricingHelper $pricingHelper,
        protected DiagnosticsLogger $logger,
        protected PriceDataFormatter $priceDataFormatter,
        protected CatalogRulesManager $catalogRulesManager,
        protected CustomerGroupsManager $customerGroupsManager,
        protected DefaultPricingStrategy $defaultPricingStrategy,
        protected CustomerGroupPricingStrategy $customerGroupPricingStrategy
    ) {}

    /**
     * @throws DiagnosticsException
     * @throws LocalizedException
     */
    public function calculatePriceData(PriceDataInterface $priceData, PricingContextInterface $pricingContext)
    : PriceDataInterface
    {
        $this->logger->startProfiling(__METHOD__);

        // Resolve pricing strategy based on customer groups configuration
        $this->pricingStrategy = $pricingContext->areCustomerGroupsEnabled()
            ? $this->customerGroupPricingStrategy
            : $this->defaultPricingStrategy;

        $priceData = $this->addDefaultPrice($priceData, $pricingContext);
        $priceData = $this->addCustomerGroupsPrices($priceData, $pricingContext);
        $priceData = $this->addSpecialPrices($priceData, $pricingContext);
        $priceData = $this->addTierPrices($priceData, $pricingContext);
        // Additional pricing logic coming from child classes
        $priceData = $this->addComplexPricing($priceData, $pricingContext);

        $this->logger->stopProfiling(__METHOD__);

        return $this->priceDataFormatter->formatPriceDataObject($priceData, $pricingContext);
    }

    protected function addDefaultPrice(PriceDataInterface $priceData, PricingContextInterface $pricingContext)
    : PriceDataInterface
    {
        $product = $pricingContext->getProduct();
        $price = $product->getPrice();
        if ($this->configHelper->isFptEnabled($product->getStoreId())) {
            $price += $this->pricingHelper->getWeeeAmount($product);
        }
        $price = $this->pricingHelper->handleConversion($price, $pricingContext);
        $price = $this->pricingHelper->getTaxPrice($product, $price, $pricingContext->shouldIncludeTax());
        $priceData->setPrice($this->pricingHelper->round($price));

        return $priceData;
    }

    protected function addCustomerGroupsPrices(PriceDataInterface $priceData, PricingContextInterface $pricingContext)
    : PriceDataInterface
    {
        return $this->pricingStrategy->applyCustomerGroupPrices($priceData, $pricingContext);
    }

    protected function addSpecialPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext
    ): PriceDataInterface
    {
        $specialPricesByGroup = $this->getSpecialPrice($pricingContext);

        $priceData = $this->pricingStrategy->applySpecialPrices($priceData, $pricingContext, $specialPricesByGroup);

        $this->addSpecialPriceDates($priceData, $pricingContext);

        return $priceData;
    }

    protected function addTierPrices(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext
    ) : PriceDataInterface
    {
        $tierPricesByGroup = $this->getTierPrice($pricingContext);

        return $this->pricingStrategy->applyTierPrices($priceData, $pricingContext, $tierPricesByGroup);
    }

    protected function addComplexPricing(PriceDataInterface $priceData, PricingContextInterface $pricingContext)
    : PriceDataInterface
    {
        // Empty for products without children
        return $priceData;
    }

    protected function getRulePrice(PricingContextInterface $pricingContext, array $catalogRules, int $groupId): float
    {
        return $catalogRules[$groupId];
    }

    protected function getSpecialPrice(PricingContextInterface $pricingContext, ?Product $subProduct = null): array
    {
        $product = $subProduct ?? $pricingContext->getProduct();
        $catalogRules = $this->catalogRulesManager->getProductRules($pricingContext, $subProduct);
        $specialPrice = [];
        $groups = $this->customerGroupsManager->getCustomerGroups($pricingContext);
        /** @var Group $group */
        foreach ($groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $specialPrices[$groupId] = [];
            $specialPrices[$groupId][] = !empty($catalogRules[$groupId]) ?
                $this->getRulePrice($pricingContext, $catalogRules, $groupId) :
                false;
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
                $specialPrice[$groupId] = $this->pricingHelper->handleConversion($specialPrice[$groupId], $pricingContext);

                $specialPrice[$groupId] = $this->pricingHelper->getTaxPrice(
                    $product,
                    $specialPrice[$groupId],
                    $pricingContext->shouldIncludeTax(),
                    false
                );
            }
        }

        return $specialPrice;
    }

    protected function getTierPrice(PricingContextInterface $pricingContext, ?Product $subProduct = null): array
    {
        $this->logger->startProfiling(__METHOD__);
        $product = $subProduct ?? $pricingContext->getProduct();
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

        $groups = $this->customerGroupsManager->getCustomerGroups($pricingContext);

        /** @var Group $group */
        foreach ($groups as $group) {
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

            $currentTierPrice = $this->pricingHelper->handleConversion($currentTierPrice, $pricingContext);
            $tierPrice[$groupId] = $this->pricingHelper->getTaxPrice(
                $product,
                $currentTierPrice,
                $pricingContext->shouldIncludeTax()
            );
        }

        $this->logger->stopProfiling(__METHOD__);

        return $tierPrice;
    }

    protected function addSpecialPriceDates(PriceDataInterface $priceData, PricingContextInterface $pricingContext)
    : PriceDataInterface
    {
        $product = $pricingContext->getProduct();

        $priceData->setSpecialFromDate(
            (!empty($product->getSpecialFromDate())) ? strtotime((string) $product->getSpecialFromDate()) : ''
        );
        $priceData->setSpecialToDate(
            (!empty($product->getSpecialToDate())) ? strtotime((string) $product->getSpecialToDate()) : ''
        );

        return $priceData;
    }
}
