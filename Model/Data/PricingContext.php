<?php

namespace Algolia\AlgoliaSearch\Model\Data;

use Algolia\AlgoliaSearch\Api\Data\PricingContextInterface as PCI;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Store\Model\Store;

class PricingContext extends DataObject implements PCI
{
    public function __construct(
        protected ConfigHelper $configHelper,
        array $data = []
    ) {
        parent::__construct($data);
    }

    public function setProduct(Product $product): void
    {
        $this->setData(self::PRODUCT, $product);
    }

    public function setSubProducts(array $subProducts): void
    {
        $this->setData(self::SUB_PRODUCTS, $subProducts);
    }

    public function setCurrencyCode(string $currencyCode): void
    {
        $this->setData(self::CURRENCY_CODE, $currencyCode);
    }

    public function setShouldIncludeTax(bool $shouldIncludeTax): void
    {
        $this->setData(self::SHOULD_INCLUDE_TAX, $shouldIncludeTax);
    }

    public function getProduct(): Product
    {
        return $this->getData(self::PRODUCT);
    }

    public function getSubProducts(): array
    {
        return $this->getData(self::SUB_PRODUCTS);
    }

    public function getCurrencyCode(): string
    {
        return $this->getData(self::CURRENCY_CODE);
    }

    public function getBaseCurrencyCode(): string
    {
        return $this->getStore()->getBaseCurrencyCode();
    }

    public function shouldIncludeTax(): bool
    {
        return $this->getData(self::SHOULD_INCLUDE_TAX);
    }

    public function getStore(): Store
    {
        return $this->getProduct()->getStore();
    }

    public function areCustomerGroupsEnabled(): bool
    {
        return $this->configHelper->isCustomerGroupsEnabled($this->getStoreId());
    }

    public function isFptEnabled(): bool
    {
        return $this->configHelper->isFptEnabled($this->getStoreId());
    }

    public function isCurrencyDifferentFromBase(): bool
    {
        return $this->getCurrencyCode() !== $this->getBaseCurrencyCode();
    }
}
