<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class PersonalizationTracking implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('Ignore')],
            ['value' => 1, 'label' => __('Send')],
        ];
    }
}
