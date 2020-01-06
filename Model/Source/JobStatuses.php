<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Algolia\AlgoliaSearch\Api\Data\JobInterface;

class JobStatuses implements \Magento\Framework\Data\OptionSourceInterface
{
    /** @return array */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => JobInterface::STATUS_NEW,
                'label' => __('New'),
            ],
            [
                'value' => JobInterface::STATUS_ERROR,
                'label' => __('Error'),
            ],
            [
                'value' => JobInterface::STATUS_PROCESSING,
                'label' => __('Processing'),
            ],
            [
                'value' => JobInterface::STATUS_COMPLETE,
                'label' => __('Complete'),
            ],
        ];

        return $options;
    }
}
