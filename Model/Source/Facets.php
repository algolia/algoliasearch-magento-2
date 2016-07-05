<?php

namespace Algolia\AlgoliaSearch\Model\Source;

class Facets extends AbstractTable
{
    protected function getTableData()
    {
        $productHelper = $this->productHelper;

        return [
            'attribute' => [
                'label'  => 'Attribute',
                'values' => function () use ($productHelper) {
                    $options = [];

                    foreach ($productHelper->getAdditionalAttributes() as $attribute) {
                        $options[$attribute['attribute']] = $attribute['attribute'];
                    }

                    return $options;
                }
            ],
            'type' => [
                'label'  => 'Facet type',
                'values' => [
                    'conjunctive' => 'Conjunctive',
                    'disjunctive' => 'Disjunctive',
                    'slider'      => 'Slider',
                    'priceRanges' => 'Price Ranges'
                ]
            ],
            'label' => [
                'label' => 'Label'
            ]
        ];
    }
}
