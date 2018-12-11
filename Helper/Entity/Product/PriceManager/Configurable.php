<?php

namespace Algolia\AlgoliaSearch\Helper\Entity\Product\PriceManager;

class Configurable extends ProductWithChildren
{
    protected function getRulePrice($groupId, $product)
    {
        $childrenPrices = [];
        /** @var \Magento\ConfigurableProduct\Model\Product\Type\Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance();
        $children = $typeInstance->getUsedProducts($product);
        foreach ($children as $child) {
            $childrenPrices[] = (float) $this->rule->getRulePrice(
                new \DateTime(),
                $this->store->getWebsiteId(),
                $groupId,
                $child->getId()
            );
        }

        return min($childrenPrices);
    }
}
