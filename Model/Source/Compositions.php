<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Compositions implements OptionSourceInterface
{
    public function toOptionArray()
    {
        // @todo fetch real compositions with appropriate service
        $compositions =  [
            [
                'value' => '',
                'label' => '',
            ],
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
