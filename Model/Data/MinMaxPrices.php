<?php

namespace Algolia\AlgoliaSearch\Model\Data;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterface as MMPI;

class MinMaxPrices implements MMPI
{
    public function __construct(
        protected float $min,
        protected float $max,
        protected float $minOriginal,
        protected float $maxOriginal,
    ){}

    public function getMin(): float
    {
        return $this->min;
    }

    public function getMax(): float
    {
        return $this->max;
    }

    public function getMinOriginal(): float
    {
        return $this->minOriginal;
    }

    public function getMaxOriginal(): float
    {
        return $this->maxOriginal;
    }
}
