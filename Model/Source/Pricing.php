<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Pricing implements OptionSourceInterface
{
    public const PRICING_V1 = 1;
    public const PRICING_V2 = 2;

    public function toOptionArray()
    {
        return [
            [
                'value' => self::PRICING_V2,
                'label' => __('Pricing V2 (new)'),
            ],
            [
                'value' => self::PRICING_V1,
                'label' => __('Pricing V1 (deprecated)'),
            ]
        ];
    }
}
