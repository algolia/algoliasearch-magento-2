<?php

namespace Algolia\AlgoliaSearch\Model\Source;

class JobClasses implements \Magento\Framework\Data\OptionSourceInterface
{
    private $classes = [
        \Algolia\AlgoliaSearch\Model\IndicesConfigurator::class => 'Model\IndicesConfigurator',
        \Algolia\AlgoliaSearch\Model\IndexMover::class => 'Model\IndexMover',
        \Algolia\AlgoliaSearch\Service\AdditionalSection\IndexBuilder::class => 'AdditionalSection\IndexBuilder',
        \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class => 'Category\IndexBuilder',
        \Algolia\AlgoliaSearch\Service\Page\IndexBuilder::class => 'Page\IndexBuilder',
        \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class => 'Product\IndexBuilder',
        \Algolia\AlgoliaSearch\Service\Suggestion\IndexBuilder::class => 'Suggestion\IndexBuilder',
        // @deprecated
        \Algolia\AlgoliaSearch\Helper\Data::class => 'Helper\Data',
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
