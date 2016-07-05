<?php

namespace Algolia\AlgoliaSearch\Model\Source;

class Synonyms extends AbstractTable
{
    protected function getTableData()
    {
        return [
            'synonyms' => [
                'label' => 'Synonyms (comma-separated)',
                'style' => 'width: 550px;',
            ]
        ];
    }
}
