<?php

namespace Algolia\AlgoliaSearch\Service\Product;

class RulePricesStorage
{
    private array $rulePrices = [];

    public function getRulePrice(string $id): array|false
    {
        return $this->rulePrices[$id] ?? false;
    }

    public function hasRulePrice(string $id): bool
    {
        return isset($this->rulePrices[$id]);
    }

    public function setRulePrice(string $id, array $prices)
    {
        $this->rulePrices[$id] = $prices;
    }
}
