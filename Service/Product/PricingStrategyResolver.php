<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Api\Product\PricingStrategyInterface;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\CustomerGroupPricingStrategy;
use Algolia\AlgoliaSearch\Service\Product\Pricing\Strategy\DefaultPricingStrategy;

class PricingStrategyResolver
{
    public function __construct(
        protected DefaultPricingStrategy $defaultPricingStrategy,
        protected CustomerGroupPricingStrategy $customerGroupPricingStrategy
    ) {}

    public function resolve(PricingContextInterface $pricingContext): PricingStrategyInterface
    {
        // Resolve pricing strategy based on customer groups configuration
        return $pricingContext->areCustomerGroupsEnabled()
            ? $this->customerGroupPricingStrategy
            : $this->defaultPricingStrategy;
    }
}
