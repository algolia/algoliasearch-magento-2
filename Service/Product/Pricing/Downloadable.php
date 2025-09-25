<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;

class Downloadable extends ProductWithoutChildren
{
    /**
     * @param Product $product
     * @param $currencyCode
     * @param $withTax
     * @param $field
     * @return void
     */
    protected function addCustomerGroupsPrices(Product $product, $currencyCode, $withTax, $field)
    {
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $product = $this->productloader->create()->load($product->getId());
            $product->setData('customer_group_id', $groupId);
            $product->setData('website_id', $product->getStore()->getWebsiteId());
            $discountedPrice = $product->getPriceInfo()->getPrice('final_price')->getValue();
            if ($currencyCode !== $this->baseCurrencyCode) {
                $discountedPrice = $this->convertPrice($discountedPrice, $currencyCode);
            }
            if ($discountedPrice !== false) {
                $this->customData[$field][$currencyCode]['group_' . $groupId] =
                    $this->getTaxPrice($product, $discountedPrice, $withTax);
                $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] =
                    $this->formatPrice(
                        $this->customData[$field][$currencyCode]['group_' . $groupId],
                        $currencyCode
                    );
                if ($this->customData[$field][$currencyCode]['default'] >
                    $this->customData[$field][$currencyCode]['group_' . $groupId]) {
                    $this->customData[$field][$currencyCode]['group_' . $groupId . '_original_formated'] =
                        $this->customData[$field][$currencyCode]['default_formated'];
                }
            } else {
                $this->customData[$field][$currencyCode]['group_' . $groupId] =
                    $this->customData[$field][$currencyCode]['default'];
                $this->customData[$field][$currencyCode]['group_' . $groupId . '_formated'] =
                    $this->customData[$field][$currencyCode]['default_formated'];
            }
        }

        $product->setData('customer_group_id', null);
    }
}
