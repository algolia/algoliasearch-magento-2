<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Magento\Store\Model\Store;

class PriceDataFormatter
{
    public function __construct(
        protected PricingHelper $pricingHelper,
        protected ConfigHelper $configHelper
    ) {}

    public function formatPriceDataObject(
        PriceDataInterface $priceData,
        Store $store,
        string $currencyCode
    ) : PriceDataInterface
    {
        $priceData = $this->formatFields($priceData, $store, $currencyCode);

        if ($this->configHelper->isCustomerGroupsEnabled($store->getId())) {
            foreach ($this->pricingHelper->getCustomerGroupCollection() as $customerGroup) {
                $priceData = $this->formatFields($priceData, $store, $currencyCode, $customerGroup->getId());
            }
        }

        return $priceData;
    }

    protected function formatFields(
        PriceDataInterface $priceData,
        Store $store,
        string $currencyCode,
        ?int $groupId = null
    ) : PriceDataInterface
    {
        if ($priceData->getPrice($groupId) > 0.00) {
            $priceData->setFormattedPrice(
                $this->pricingHelper->formatPrice(
                    $priceData->getPrice(),
                    $store,
                    $currencyCode
                ),
                $groupId
            );
        }

        if ($priceData->getOriginalPrice() > 0.00) {
            $priceData->setFormattedOriginalPrice(
                $this->pricingHelper->formatPrice(
                    $priceData->getOriginalPrice(),
                    $store,
                    $currencyCode
                ),
                $groupId
            );
        }

        return $priceData;
    }
}
