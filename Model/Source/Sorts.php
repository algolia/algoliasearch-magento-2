<?php

namespace Algolia\AlgoliaSearch\Model\Source;

/**
 * Algolia custom sort order field
 */
class Sorts extends AbstractTable
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
            'sort' => [
                'label'  => 'Sort',
                'values' => [
                    'asc'  => 'Ascending',
                    'desc' => 'Descending',
                ],
            ],
            'label' => [
                'label' => 'Label',
            ],
        ];
    }
}
