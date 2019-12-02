<?php

namespace Algolia\AlgoliaSearch\Model\Source;

class JobMethods implements \Magento\Framework\Data\OptionSourceInterface
{
    private $methods = [
        'saveConfigurationToAlgolia' => 'Save Configuration',
        'moveIndexWithSetSettings' => 'Move Index',
        'deleteObjects' => 'Object deletion',
        'rebuildStoreCategoryIndex' => 'Category Reindex',
        'rebuildStoreProductIndex' => 'Product Reindex',
        'rebuildProductIndex' => 'Product Reindex',
        'rebuildStoreAdditionalSectionsIndex' => 'Additional Section Reindex',
        'rebuildStoreSuggestionIndex' => 'Suggestion Reindex',
        'rebuildStorePageIndex' => 'Page Reindex',
    ];

    /** @return array */
    public function toOptionArray()
    {
        $options = [];

        foreach ($this->methods as $key => $value) {
            $options[] = [
                'value' => $key,
                'label' => __($value),
            ];
        }

        return $options;
    }
}
