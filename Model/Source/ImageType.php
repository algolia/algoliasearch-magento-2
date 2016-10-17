<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class ImageType implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'image',         'label' => __('Base Image')],
            ['value' => 'small_image',   'label' => __('Small Image')],
            ['value' => 'thumbnail',     'label' => __('Thumbnail')],
        ];
    }
}
