<?php

namespace Algolia\AlgoliaSearch\Model\Source;

class CustomRankingCategory extends AbstractTable
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
            'order' => [
                'label'  => 'Order',
                'values' => ['asc' => 'Ascending', 'desc' => 'Descending'],
            ],
        ];
    }
}
