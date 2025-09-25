<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use DateTime;

class Configurable extends ProductWithChildren
{
    /**
     * @param $groupId
     * @param $product
     * @param $subProducts
     * @return float|int|mixed
     */
    protected function getRulePrice($groupId, $product, $subProducts)
    {
        $childrenPrices = [];
        $typeInstance = $product->getTypeInstance();

        if (!$typeInstance instanceof \Magento\ConfigurableProduct\Model\Product\Type\Configurable) {
            $this->logger->log('Unexpected product type encountered, reverting to default price calculation. Where Product Id is ' .$product->getId(). ' and Group Id is ' .$groupId);
            return parent::getRulePrice($groupId, $product, $subProducts);
        }

        foreach ($subProducts as $child) {
            $childrenPrices[] = (float) $this->rule->getRulePrice(
                new DateTime(),
                $this->store->getWebsiteId(),
                $groupId,
                $child->getId()
            );
        }
        if ($childrenPrices === []) {
            return 0;
        }
        return min($childrenPrices);
    }
}
