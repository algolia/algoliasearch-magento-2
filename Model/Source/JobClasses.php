<?php

namespace Algolia\AlgoliaSearch\Model\Source;

class JobClasses implements \Magento\Framework\Data\OptionSourceInterface
{
    private $classes = [
        'Algolia\AlgoliaSearch\Model\IndicesConfigurator' => 'Model\IndicesConfigurator',
        'Algolia\AlgoliaSearch\Model\IndexMover' => 'Model\IndexMover',
        'Algolia\AlgoliaSearch\Service\AdditionalSection\IndexBuilder' => 'AdditionalSection\IndexBuilder',
        'Algolia\AlgoliaSearch\Service\Category\IndexBuilder' => 'Category\IndexBuilder',
        'Algolia\AlgoliaSearch\Service\Page\IndexBuilder' => 'Page\IndexBuilder',
        'Algolia\AlgoliaSearch\Service\Product\IndexBuilder' => 'Product\IndexBuilder',
        'Algolia\AlgoliaSearch\Service\Suggestion\IndexBuilder' => 'Suggestion\IndexBuilder',
        // @deprecated
        'Algolia\AlgoliaSearch\Helper\Data' => 'Helper\Data',
    ];

    /** @return array */
    public function toOptionArray()
    {
        $options = [];

        foreach ($this->classes as $key => $value) {
            $options[] = [
                'value' => $key,
                'label' => __($value),
            ];
        }

        return $options;
    }
}
