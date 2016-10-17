<?php

namespace Algolia\AlgoliaSearch\Model\Source;

class CustomRankingProduct extends AbstractTable
{
    protected function getTableData()
    {
        $productHelper = $this->productHelper;

        return [
            'attribute' => [
                'label'  => 'Attribute',
                'values' => function () use ($productHelper) {
                    $options = [];
                    $attributes = $productHelper->getAdditionalAttributes();

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
