<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

class Configurable extends ProductWithChildren
{
    /**
     * @return float|int|mixed
     */
    protected function getRulePrice($groupId, $product, $subProducts)
    {
        $childrenPrices = [];
        $typeInstance = $product->getTypeInstance();

        if (!$typeInstance instanceof \Magento\ConfigurableProduct\Model\Product\Type\Configurable) {
            $this->logger->debug(
                'Sub product iteration within configurable, reverting to default price calculation.',
                [ 'entity_id' => $product->getId(), 'group_id' => $groupId ]
            );

            return parent::getRulePrice($groupId, $product, $subProducts);
        }

        foreach ($subProducts as $child) {
            $childrenPrices[] = $this->pricingHelper->getRulePrice($groupId, $child);
        }
        if ($childrenPrices === []) {
            return 0;
        }

        return min($childrenPrices);
    }
}
