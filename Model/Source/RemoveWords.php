<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class RemoveWords implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'none',          'label' => __('None')],
            ['value' => 'allOptional',   'label' => __('AllOptional')],
            ['value' => 'lastWords',     'label' => __('LastWords')],
            ['value' => 'firstWords',    'label' => __('FirstWords')],
        ];
    }
}
