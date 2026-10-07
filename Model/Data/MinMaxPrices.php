<?php

namespace Algolia\AlgoliaSearch\Model\Data;

use Algolia\AlgoliaSearch\Api\Data\MinMaxPricesInterface as MMPI;
use Magento\Framework\DataObject;

class MinMaxPrices extends DataObject implements MMPI
{
    public function getMin(): float
    {
        return $this->getData(MMPI::MIN);
    }

    public function getMax(): float
    {
        return $this->getData(MMPI::MAX);
    }

    public function getMinOriginal(): float
    {
        return $this->getData(MMPI::MIN_ORIGINAL);
    }

    public function getMaxOriginal(): float
    {
        return $this->getData(MMPI::MAX_ORIGINAL);
    }
}
