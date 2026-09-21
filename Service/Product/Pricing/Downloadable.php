<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;
use Magento\Catalog\Model\ProductFactory;

class Downloadable extends ProductWithoutChildren
{
    public function __construct(
        protected ProductFactory $productFactory,
        protected ConfigHelper $configHelper,
        protected PricingHelper $pricingHelper,
        protected DiagnosticsLogger $logger
    ) {
        parent::__construct(
            $configHelper,
            $pricingHelper,
            $logger
        );
    }

    /**
     * @param array $priceData
     * @param Product $product
     * @param $currencyCode
     * @param $withTax
     * @return array
     */
    protected function addCustomerGroupsPrices(array $priceData, Product $product, $currencyCode, $withTax): array
    {
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $product = $this->productFactory->create()->load($product->getId());
            $product->setData('customer_group_id', $groupId);
            $product->setData('website_id', $product->getStore()->getWebsiteId());
            $discountedPrice = $product->getPriceInfo()->getPrice('final_price')->getValue();

            if ($currencyCode !== $this->baseCurrencyCode) {
                $discountedPrice = $this->pricingHelper->convertPrice($discountedPrice, $this->store, $currencyCode);
            }

            if ($discountedPrice !== false) {
                $priceData[$currencyCode]['group_' . $groupId] =
                    $this->pricingHelper->getTaxPrice($product, $discountedPrice, $withTax);
                $priceData[$currencyCode]['group_' . $groupId . '_formated'] =
                    $this->pricingHelper->formatPrice(
                        $priceData[$currencyCode]['group_' . $groupId],
                        $this->store,
                        $currencyCode
                    );
                if ($priceData[$currencyCode]['default'] >
                    $priceData[$currencyCode]['group_' . $groupId]) {
                    $priceData[$currencyCode]['group_' . $groupId . '_original_formated'] =
                        $priceData[$currencyCode]['default_formated'];
                }
            } else {
                $priceData[$currencyCode]['group_' . $groupId] =
                    $priceData[$currencyCode]['default'];
                $priceData[$currencyCode]['group_' . $groupId . '_formated'] =
                    $priceData[$currencyCode]['default_formated'];
            }
        }

        $product->setData('customer_group_id', null);

        return $priceData;
    }
}
