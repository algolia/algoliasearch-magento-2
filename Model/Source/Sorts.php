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

                    foreach ($productHelper->getAdditionalAttributes() as $attribute) {
                        $options[$attribute['attribute']] = $attribute['attribute'];
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
