<?php

namespace Algolia\AlgoliaSearch\Api\Data;

use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;

interface PricingContextInterface
{
    public const PRODUCT = 'product';
    public const SUB_PRODUCTS = 'sub_products';
    public const CURRENCY_CODE = 'currency_code';
    public const USE_TAX = 'use_tax';

    public function getProduct(): Product;

    public function getSubProducts(): array;

    public function getCurrencyCode(): string;

    public function getBaseCurrencyCode(): string;

    public function useTax(): bool;

    public function getStore(): Store;

    public function areCustomerGroupsEnabled(): bool;

    public function isFptEnabled(): bool;

    public function isCurrencyDifferentFromBase(): bool;
}
