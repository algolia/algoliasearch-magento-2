<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class BackendRenderingDisplayMode implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'all',           'label' => __('All categories')],
            ['value' => 'only_products', 'label' => __('Categories without static blocks')],
        ];
    }
}
