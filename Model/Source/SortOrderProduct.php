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
                'values' => ['1' => 'Yes', '2' => 'No'],
            ],
            'order' => [
                'label'  => 'Ordered?',
                'values' => ['unordered' => 'Unordered', 'ordered' => 'Ordered'],
            ],
            'retrievable' => [
                'label'  => 'Retrievable?',
                'values' => ['1' => 'Yes', '2' => 'No'],
            ],
        ];
    }
}
