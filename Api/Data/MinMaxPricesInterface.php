<?php

namespace Algolia\AlgoliaSearch\Api\Data;

interface MinMaxPricesInterface
{
    public const MIN = 'min';
    public const MAX = 'max';
    public const MIN_ORIGINAL = 'min_original';
    public const MAX_ORIGINAL = 'max_original';

    public function getMin(): float;
    public function getMax(): float;
    public function getMinOriginal(): float;
    public function getMaxOriginal(): float;
}
