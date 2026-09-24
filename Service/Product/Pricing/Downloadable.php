<?php

namespace Algolia\AlgoliaSearch\Service\Product\Pricing;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterfaceFactory;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\PricingHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;

class Downloadable extends AbstractProduct
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
        protected ConfigHelper $configHelper,
        protected PricingHelper $pricingHelper,
        protected PriceDataInterfaceFactory $priceDataInterfaceFactory,
        protected DiagnosticsLogger $logger
    ) {
        parent::__construct(
            $configHelper,
            $pricingHelper,
            $priceDataInterfaceFactory,
            $logger
        );
    }

    protected function addCustomerGroupsPrices(Product $product, $currencyCode, $withTax): void
    {
        /** @var Group $group */
        foreach ($this->groups as $group) {
            $groupId = (int) $group->getData('customer_group_id');
            $product = $this->productRepository->getById($product->getId(), false, $product->getStoreId(), true);
            $product->setData('customer_group_id', $groupId);
            $product->setData('website_id', $product->getStore()->getWebsiteId());
            $discountedPrice = $product->getPriceInfo()->getPrice('final_price')->getValue();

            if ($currencyCode !== $this->baseCurrencyCode) {
                $discountedPrice = $this->pricingHelper->convertPrice($discountedPrice, $this->store, $currencyCode);
            }

            if ($discountedPrice !== false) {
                $this->priceData->setPrice(
                    $this->pricingHelper->getTaxPrice($product, $discountedPrice, $withTax),
                    $groupId
                );
                $this->priceData->setFormatedPrice(
                    $this->pricingHelper->formatPrice(
                        $this->priceData->getPrice($groupId),
                        $this->store,
                        $currencyCode
                    ),
                    $groupId
                );

                if ($this->priceData->getPrice() > $this->priceData->getPrice($groupId)) {
                    $this->priceData->setFormatedOriginalPrice($this->priceData->getFormatedPrice(), $groupId);
                }
            } else {
                $this->priceData->setPrice($this->priceData->getPrice(), $groupId);
                $this->priceData->setFormatedPrice($this->priceData->getFormatedPrice(), $groupId);
            }
        }

        $product->setData('customer_group_id', null);
    }
}
