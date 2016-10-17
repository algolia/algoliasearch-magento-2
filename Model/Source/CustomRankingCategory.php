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
                    $attributes = $categoryHelper->getAdditionalAttributes();

                    foreach ($attributes as $attribute) {
                        $options[$attribute['attribute']] = $attribute['attribute'];
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
