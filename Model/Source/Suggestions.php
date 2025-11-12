<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Suggestions implements OptionSourceInterface
{

    public const SUGGESTIONS_DISABLED = 0;
    public const SUGGESTIONS_MAGENTO = 1;
    public const SUGGESTIONS_ALGOLIA = 2;

    public function toOptionArray()
    {
        return [
            [
                'value' => self::SUGGESTIONS_DISABLED,
                'label' => __('No'),
            ],
            [
                'value' => self::SUGGESTIONS_MAGENTO,
                'label' => __('Use Magento Search Queries (deprecated)'),
            ],
            [
                'value' => self::SUGGESTIONS_ALGOLIA,
                'label' => __('Use Algolia Query Suggestions'),
            ],
        ];
    }
}
