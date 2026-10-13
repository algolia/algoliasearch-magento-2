<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Magento\CatalogRule\Model\ResourceModel\RuleFactory;
use Magento\CatalogRule\Observer\RulePricesStorage;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;

class CatalogRulesManager
{
    public function __construct(
        protected RulePricesStorage $rulePricesStorage,
        protected RuleFactory $resourceRuleFactory,
        protected GroupManagementInterface $groupManagement,
        protected TimezoneInterface $localeDate
    ){}

    public function getProductRules(Store $store, array $productIds = [], ?int $groupId = null): array
    {
        if (empty($productIds)) {
            return [];
        }

        $websiteId = $store->getWebsiteId();
        $groupId = $groupId ?? $this->groupManagement->getNotLoggedInGroup()->getId();
        $date = (new \DateTime())->setTimestamp($this->localeDate->scopeTimeStamp($store));

        // @todo add cache logic

        $rulePrices = $this->resourceRuleFactory->create()->getRulePrices(
            $date,
            $websiteId,
            $groupId,
            $productIds
        );

        foreach ($productIds as $productId) {
            $key = implode('|', [$date->format('Y-m-d H:i:s'), $websiteId, $groupId, $productId]);
            $this->rulePricesStorage->setRulePrice($key, $rulePrices[$productId] ?? false);
        }

        return $rulePrices;
    }
}
