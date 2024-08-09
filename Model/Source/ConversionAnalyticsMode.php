<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Historically the Algolia Magento integration has provided the ability to consider a conversion either a
 * purchase or an add to cart operation.
 *
 * With the introduction of Revenue Analytics which supports both types out of the box this kind of granularity
 * does not make as much sense. However, the ability to support this feature remains in concept by not configuring
 * this setting as a Yes/No flag.
 */
class ConversionAnalyticsMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => InsightsHelper::CONVERSION_ANALYTICS_MODE_ALL, 'label' => __('Yes')],
            ['value' => InsightsHelper::CONVERSION_ANALYTICS_MODE_DISABLE, 'label' => __('No')]
        ];
    }
}
