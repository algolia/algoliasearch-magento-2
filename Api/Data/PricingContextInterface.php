<?php

namespace Algolia\AlgoliaSearch\Api\Data;

use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;

interface PricingContextInterface
{
    public const PRODUCT = 'product';
    public const SUB_PRODUCTS = 'sub_products';
    public const CURRENCY_CODE = 'currency_code';
    public const SHOULD_INCLUDE_TAX = 'should_include_tax';

    public function setProduct(Product $product): void;

    public function setSubProducts(array $subProducts): void;

    public function setCurrencyCode(string $currencyCode): void;

    public function setShouldIncludeTax(bool $shouldIncludeTax): void;

    public function getProduct(): Product;

    public function getSubProducts(): array;

    public function getCurrencyCode(): string;

    public function getBaseCurrencyCode(): string;

    public function shouldIncludeTax(): bool;

    public function getStore(): Store;

    public function areCustomerGroupsEnabled(): bool;

    public function isFptEnabled(): bool;

    public function isCurrencyDifferentFromBase(): bool;
}
