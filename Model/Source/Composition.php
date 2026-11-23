<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Composition implements OptionSourceInterface
{
    public function toOptionArray()
    {
        // @todo fetch real compositions with appropriate service
        $compositions =  [
            [
                'value' => 'foo',
                'label' => 'FOO',
            ],
            [
                'value' => 'bar',
                'label' => 'BAR',
            ]
        ];

        return $compositions;
    }
}
