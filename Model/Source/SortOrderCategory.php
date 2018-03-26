<?php

namespace Algolia\AlgoliaSearch\Model\Source;

class SortOrderCategory extends AbstractTable
{
    protected function getTableData()
    {
        $categoryHelper = $this->categoryHelper;

        return [
            'attribute' => [
                'label'  => 'Attribute',
                'values' => function () use ($categoryHelper) {
                    $options = [];
                    $attributes = $categoryHelper->getAllAttributes();

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
            'retrievable' => [
                'label'  => 'Retrievable?',
                'values' => ['1' => 'Yes', '2' => 'No'],
            ],
            'order' => [
                'label'  => 'Ordered?',
                'values' => ['unordered' => 'Unordered', 'ordered' => 'Ordered'],
            ],
        ];
    }
}
