<?php

namespace Algolia\AlgoliaSearch\Model\Source;

class SortOrderProduct extends AbstractTable
{
    protected function getTableData()
    {
        $productHelper = $this->productHelper;

        return [
            'attribute' => [
                'label'  => 'Attribute',
                'values' => function () use ($productHelper) {
                    $options = [];
                    $attributes = $productHelper->getAllAttributes();

                    foreach ($attributes as $key => $label) {
                        $options[$key] = $key ? $key : $label;
                    }

                    return $options;
                },
            ],
            'searchable' => [
                'label'  => 'Searchable?',
                'values' => ['1' => __('Yes'), '2' => __('No')],
            ],
            'order' => [
                'label'  => 'Ordered?',
                'values' => ['unordered' => __('Unordered'), 'ordered' => __('Ordered')],
            ],
            'retrievable' => [
                'label'  => 'Retrievable?',
                'values' => ['1' => __('Yes'), '2' => __('No')],
            ],
        ];
    }
}
