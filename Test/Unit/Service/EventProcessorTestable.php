<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Service\Insights\EventProcessor;

class EventProcessorTestable extends EventProcessor
{
    public function getObjectDataForPurchase(...$params): array
    {
        return parent::getObjectDataForPurchase(...$params);
    }

    public function getTotalRevenueForEvent(...$params): float
    {
        return parent::getTotalRevenueForEvent(...$params);
    }
}
