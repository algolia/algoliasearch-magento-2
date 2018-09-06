<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Algolia\AlgoliaSearch\Api\Data\JobInterface;

class JobMethods implements \Magento\Framework\Data\OptionSourceInterface
{
    /** @return array */
    public function toOptionArray()
    {
        $options = [];

        foreach (JobInterface::METHODS as $key => $value) {
            $options[] = [
                'value' => $key,
                'label' => $value,
            ];
        }

        return $options;
    }
}
