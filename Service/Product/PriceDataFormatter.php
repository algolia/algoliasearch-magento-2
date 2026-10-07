<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;

class PriceDataFormatter
{
    public function __construct(
        protected PricingHelper $pricingHelper,
        protected ConfigHelper $configHelper
    ) {}

    public function formatPriceDataObject(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext
    ) : PriceDataInterface
    {
        $priceData = $this->formatFields($priceData, $pricingContext);

        if ($this->configHelper->isCustomerGroupsEnabled($pricingContext->getStore())) {
            foreach ($this->pricingHelper->getCustomerGroupCollection() as $customerGroup) {
                $priceData = $this->formatFields($priceData, $pricingContext, $customerGroup->getId());
            }
        }

        return $priceData;
    }

    protected function formatFields(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext,
        ?int $groupId = null
    ) : PriceDataInterface
    {
        $store = $pricingContext->getStore();
        $currencyCode = $pricingContext->getCurrencyCode();

        // Basic price
        if (is_float($priceData->getPrice($groupId))) {
            $priceData->setFormattedPrice(
                $this->pricingHelper->formatPrice(
                    $priceData->getPrice($groupId),
                    $store,
                    $currencyCode
                ),
                $groupId
            );
        }

        // Min/Max prices for configurable/bundle/grouped
        if ((is_float($priceData->getMinPrice($groupId)) &&
            is_float($priceData->getMaxPrice($groupId))) &&
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
        if (is_float($priceData->getOriginalPrice($groupId)) &&
            $priceData->getOriginalPrice($groupId) > $priceData->getPrice($groupId)) {
            $priceData->setFormattedOriginalPrice(
                $this->pricingHelper->formatPrice(
                    $priceData->getOriginalPrice($groupId),
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
