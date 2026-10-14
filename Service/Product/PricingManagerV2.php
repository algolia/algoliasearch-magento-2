<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Data\PriceDataInterfaceFactory;
use Algolia\AlgoliaSearch\Api\Data\PricingContextInterfaceFactory;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Product\Pricing\AbstractProduct as Pricing;
use Magento\Catalog\Model\Product;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Tax\Model\Config as TaxConfig;

class PricingManagerV2
{
    public const string DEFAULT_PM = 'priceManager';
    public const string DEFAULT_MANAGER = 'default';
    public const string GROUPS_MANAGER = 'customer_groups';

    public function __construct(
        protected TaxHelper $taxHelper,
        protected ConfigHelper $configHelper,
        protected PriceDataInterfaceFactory $priceDataFactory,
        protected PricingContextInterfaceFactory $pricingContextFactory,
        protected array $priceManagers = [],
    ) {}

    public function addPriceDataByProductType(array $customData, Product $product, array $subProducts): array
    {
        $priceManager = $this->resolvePriceManager($product);

        $priceFields = $this->getPriceFields($product);
        $currencies = $product->getStore()->getAvailableCurrencyCodes(true);

        // price/price_with_tax => true/false
        foreach ($priceFields as $field => $shouldIncludeTax) {
            $customData[$field] = [];
            foreach ($currencies as $currencyCode) {
                $pricingContext = $this->pricingContextFactory->create();
                $pricingContext->setProduct($product);
                $pricingContext->setSubProducts($subProducts);
                $pricingContext->setCurrencyCode($currencyCode);
                $pricingContext->setShouldIncludeTax($shouldIncludeTax);

                $priceArray = $priceManager->calculatePriceData(
                    $this->priceDataFactory->create(),
                    $pricingContext
                )->toArray();

                ksort($priceArray);

                $customData[$field][$currencyCode] = $priceArray;
            }
        }

        return $customData;
    }

    protected function resolvePriceManager(Product $product): Pricing
    {
        $priceManager = self::DEFAULT_PM . ucfirst($product->getTypeId());
        $key = $this->configHelper->isCustomerGroupsEnabled($product->getStoreId()) ?
            self::GROUPS_MANAGER :
            self::DEFAULT_MANAGER;

        if (isset($this->priceManagers[$key][$priceManager])) {
            return $this->priceManagers[$key][$priceManager];
        }

        return $this->priceManagers[self::DEFAULT_MANAGER][self::DEFAULT_PM . 'Simple'];
    }

    protected function getPriceFields(Product $product): array
    {
        $priceDisplayType = $this->taxHelper->getPriceDisplayType($product->getStore());

        if ($priceDisplayType === TaxConfig::DISPLAY_TYPE_BOTH) {
            return ['price' => false, 'price_with_tax' => true];
        }

        return ['price' => $priceDisplayType === TaxConfig::DISPLAY_TYPE_INCLUDING_TAX];
    }
}
