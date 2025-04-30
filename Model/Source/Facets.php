<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Algolia\AlgoliaSearch\Service\Product\FacetBuilder;

class Facets extends AbstractTable
{
    protected function getTableData()
    {
        $productHelper = $this->productHelper;

        $config = [
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
            'type' => [
                'label'  => 'Facet type',
                'values' => [
                    'conjunctive' => 'Conjunctive',
                    'disjunctive' => 'Disjunctive',
                    'slider'      => 'Slider',
                    'priceRanges' => 'Price Range',
                ],
            ],
            'label' => [
                'label' => 'Label',
            ],
            'searchable' => [
                'label'  => 'Options',
                'values' => [
                    FacetBuilder::FACET_SEARCHABLE_SEARCHABLE     => 'Searchable',
                    FacetBuilder::FACET_SEARCHABLE_NOT_SEARCHABLE => 'Not Searchable',
                    FacetBuilder::FACET_SEARCHABLE_FILTER_ONLY    => 'Filter Only'
                ],
            ],
        ];

        $config['create_rule'] =  [
            'label'  => 'Create Query rule?',
            'values' => ['2' => 'No', '1' => 'Yes'],
        ];

        return $config;
    }
}
