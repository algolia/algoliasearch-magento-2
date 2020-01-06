<?php

namespace Algolia\AlgoliaSearch\Model\Source;

class LandingPageStatuses implements \Magento\Framework\Data\OptionSourceInterface
{
    /** @return array */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => 0,
                'label' => __('Not Published'),
            ],
            [
                'value' => 1,
                'label' => __('Published'),
            ],
        ];

        return $options;
    }
}
