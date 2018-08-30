<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class ImageType implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'product_base_image',         'label' => __('Base Image')],
            ['value' => 'product_small_image',        'label' => __('Small Image')],
            ['value' => 'product_thumbnail_image',    'label' => __('Thumbnail')],
        ];
    }
}
