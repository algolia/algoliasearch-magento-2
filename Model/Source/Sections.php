<?php

namespace Algolia\AlgoliaSearch\Model\Source;

class Sections extends AbstractTable
{
    protected function getTableData()
    {
        $config = $this->config;

        return [
            'name' => [
                'label'  => 'Section',
                'values' => function () use ($config) {
                    $options = [];

                    $sections = [
                        ['name' => 'pages', 'label' => 'Pages'],
                    ];

                    $attributes = $config->getFacets();

                    foreach ($attributes as $attribute) {
                        if ($attribute['attribute'] == 'price') {
                            continue;
                        }

                        if ($attribute['attribute'] == 'category' || $attribute['attribute'] == 'categories') {
                            continue;
                        }

                        $sections[] = [
                            'name' => $attribute['attribute'],
                            'label' => $attribute['label'] ? $attribute['label'] : $attribute['attribute'],
                        ];
                    }

                    foreach ($sections as $section) {
                        $options[$section['name']] = $section['label'];
                    }

                    return $options;
                },
            ],
            'label' => [
                'label' => 'Label',
            ],
            'hitsPerPage' => [
                'label' => 'Hits per page',
                'class' => 'validate-digits',
            ],
        ];
    }
}
