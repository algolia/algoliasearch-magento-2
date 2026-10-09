<?php

namespace Algolia\AlgoliaSearch\Api\Data;

interface MinMaxPricesInterface
{
    public function getMin(): float;
    public function getMax(): float;
    public function getMinOriginal(): float;
    public function getMaxOriginal(): float;
}
