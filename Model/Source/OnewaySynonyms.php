<?php

namespace Algolia\AlgoliaSearch\Model\Source;

class OnewaySynonyms extends AbstractTable
{
    protected function getTableData()
    {
        return [
            'input' => [
                'label' => 'Input',
                'style' => 'width: 100px;',
            ],
            'synonyms' => [
                'label' => 'Synonyms (comma-separated)',
                'style' => 'width: 435px;',
            ],
        ];
    }
}
