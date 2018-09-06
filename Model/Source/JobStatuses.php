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
                'label' => 'New',
            ],
            [
                'value' => JobInterface::STATUS_ERROR,
                'label' => 'Error',
            ],
            [
                'value' => JobInterface::STATUS_PROCESSING,
                'label' => 'Processing',
            ],
            [
                'value' => JobInterface::STATUS_COMPLETE,
                'label' => 'Complete',
            ],
        ];

        return $options;
    }
}
