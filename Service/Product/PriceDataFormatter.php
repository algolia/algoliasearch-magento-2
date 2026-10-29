<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterface;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Service\Product\Pricing\CustomerGroups\CustomerGroupsManager;

class PriceDataFormatter
{
    public function __construct(
        protected PricingHelper $pricingHelper,
        protected ConfigHelper $configHelper,
        protected CustomerGroupsManager $customerGroupsManager
    ) {}

    public function formatPriceDataObject(
        PriceDataInterface $priceData,
        PricingContextInterface $pricingContext
    ) : PriceDataInterface
    {
        $priceData = $this->formatFields($priceData, $pricingContext);

        if ($this->configHelper->isCustomerGroupsEnabled($pricingContext->getStore())) {
            foreach ($this->customerGroupsManager->getCustomerGroups($pricingContext) as $customerGroup) {
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
        if ($priceData->hasPrice($groupId)) {
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
        if (($priceData->hasMinPrice($groupId) &&
            $priceData->hasMaxPrice($groupId)) &&
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
        if ($priceData->hasOriginalPrice($groupId) &&
            $priceData->getOriginalPrice($groupId) > $priceData->getPrice($groupId)) {
            $priceData->setFormattedOriginalPrice(
                $this->pricingHelper->formatPrice(
                    $priceData->getOriginalPrice($groupId),
                    $store,
                    $currencyCode
                ),
                $groupId
            );

            // Replace with original range in case min original is lower than min
            if ($priceData->hasMinOriginalPrice($groupId) &&
                $priceData->getMinOriginalPrice($groupId) > $priceData->getMinPrice($groupId) &&
                $priceData->getMinOriginalPrice($groupId) < $priceData->getOriginalPrice($groupId)) {
                $priceData->setFormattedOriginalPrice(
                    $this->pricingHelper->formatPrice(
                        $priceData->getMinOriginalPrice($groupId),
                        $store,
                        $currencyCode
                    ) . ' - ' .
                    $this->pricingHelper->formatPrice(
                        $priceData->getOriginalPrice($groupId),
                        $store,
                        $currencyCode
                    ),
                    $groupId
                );
            }
        }

        // Tier price
        if ($priceData->hasTierPrice($groupId)) {
            $priceData->setFormattedTierPrice(
                $this->pricingHelper->formatPrice(
                    $priceData->getTierPrice($groupId),
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
