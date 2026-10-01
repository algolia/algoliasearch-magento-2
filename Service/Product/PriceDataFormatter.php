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
        // Basic price
        if ($priceData->getPrice($groupId)) {
            $priceData->setFormattedPrice(
                $this->pricingHelper->formatPrice(
                    $priceData->getPrice(),
                    $store,
                    $currencyCode
                ),
                $groupId
            );
        }

        // Min/Max prices for configurable/bundle/grouped
        if (($priceData->getMinPrice($groupId) && $priceData->getMaxPrice($groupId)) &&
            $priceData->getMinPrice($groupId) < $priceData->getMaxPrice($groupId))
        {
            $priceData->setFormattedPrice(
                $this->pricingHelper->formatPrice(
                    $priceData->getMinPrice($groupId),
                    $store,
                    $currencyCode
                ) . ' - ' .
                $this->pricingHelper->formatPrice(
                    $priceData->getMaxPrice($groupId),
                    $store,
                    $currencyCode
                ),
                $groupId
            );
        }

        // Original price for crossed-out price
        if ($priceData->getOriginalPrice()) {
            $priceData->setFormattedOriginalPrice(
                $this->pricingHelper->formatPrice(
                    $priceData->getOriginalPrice(),
                    $store,
                    $currencyCode
                ),
                $groupId
            );
        }

        // Clean values to reduce the price object size
        $priceData->unsetMinPrice($groupId);
        $priceData->unsetMaxPrice($groupId);

        return $priceData;
    }
}
