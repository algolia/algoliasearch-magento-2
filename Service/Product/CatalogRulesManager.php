<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Model\ResourceModel\RuleFactory;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class CatalogRulesManager
{
    private array $rulePrices = [];

    public function __construct(
        protected RuleFactory $resourceRuleFactory,
        protected GroupManagementInterface $groupManagement,
        protected TimezoneInterface $localeDate
    ){}

    public function getProductRules(PricingContextInterface $pricingContext, ?Product $subProduct = null): array
    {
        $websiteId = $pricingContext->getStore()->getWebsiteId();
        $date = (new \DateTime())->setTimestamp($this->localeDate->scopeTimeStamp($pricingContext->getStore()));
        // Fetch all rules for the product and its subproducts all at once
        $products = array_merge([$pricingContext->getProduct()], $pricingContext->getSubProducts());

        $rulesToFetch = [];
        foreach ($products as $product) {
            $key = $this->getStorageKey($product->getId(), $websiteId);
            if (!$this->hasRulePrice($key)) {
                $rulesToFetch[] = $product->getId();
            }
        }

        if (!empty($rulesToFetch)) {
            $this->setRulesInStorage($rulesToFetch, $date, $websiteId);
        }

        return $this->getRulesFromStorage(
            $pricingContext,
            $subProduct ? $subProduct->getId() : $pricingContext->getProduct()->getId()
        );
    }

    protected function getStorageKey(int $productId, int $websiteId): string
    {
        return implode('|', [$productId, $websiteId]);
    }

    protected function getRulesFromStorage(PricingContextInterface $pricingContext, int $productId): array
    {
        $key = $this->getStorageKey($productId, $pricingContext->getStore()->getWebsiteId());

        return $this->getRulePrice($key);
    }

    protected function setRulesInStorage(array $productIds, \DateTime $date, int $websiteId): void
    {
        $rulePrices = $this->resourceRuleFactory->create()->getAllRulePricesFromProduct(
            $date,
            $websiteId,
            $productIds
        );

        $prices = [];
        foreach ($rulePrices as $rulePrice) {
            $key = $this->getStorageKey($rulePrice['product_id'], $websiteId);
            $prices[$key][$rulePrice['customer_group_id']] = (float) $rulePrice['rule_price'];
        }

        foreach ($productIds as $productId) {
            $key = $this->getStorageKey($productId, $websiteId);
            $this->setRulePrice($key, $prices[$key] ?? []);
        }
    }

    protected function getRulePrice(string $id): array|false
    {
        return $this->rulePrices[$id] ?? false;
    }

    protected function hasRulePrice(string $id): bool
    {
        return isset($this->rulePrices[$id]);
    }

    protected function setRulePrice(string $id, array $prices): void
    {
        $this->rulePrices[$id] = $prices;
    }
}
