<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;

class Configurable extends AbstractProductWithChildren
{
    protected function getRulePrice(PricingContextInterface $pricingContext, array $catalogRules, int $groupId): float
    {
        if (empty($pricingContext->getSubProducts())) {
            return parent::getRulePrice($pricingContext, $catalogRules, $groupId);
        }

        $childrenPrices = [];
        $product = $pricingContext->getProduct();
        $typeInstance = $product->getTypeInstance();

        if (!$typeInstance instanceof \Magento\ConfigurableProduct\Model\Product\Type\Configurable) {
            $this->logger->debug(
                'Sub product iteration within configurable, reverting to default price calculation.',
                [ 'entity_id' => $product->getId(), 'group_id' => $groupId ]
            );

            return parent::getRulePrice($pricingContext, $catalogRules, $groupId);
        }

        foreach ($pricingContext->getSubProducts() as $child) {
            $childCatalogRules = $this->catalogRulesManager->getProductRules($pricingContext, $child);
            $childrenPrices[] = $childCatalogRules[$groupId];
        }

        return min($childrenPrices);
    }
}
