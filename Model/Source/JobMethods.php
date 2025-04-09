<?php

namespace Algolia\AlgoliaSearch\Model\Source;

class JobMethods implements \Magento\Framework\Data\OptionSourceInterface
{
    private $methods = [
        'saveConfigurationToAlgolia' => 'Save Configuration',
        'moveIndexWithSetSettings' => 'Move Index',
        'buildIndexFull' => 'Build Full Index',
        'buildIndexList' => 'Update Index',
        'deleteInactiveProducts' => 'Delete Inactive Products',
        // @deprecated
        'deleteObjects' => 'Object deletion (deprecated)',
        'rebuildStoreCategoryIndex' => 'Category Reindex (deprecated)',
        'rebuildCategoryIndex' => 'Category Reindex (deprecated)',
        'rebuildStoreProductIndex' => 'Product Reindex (deprecated)',
        'rebuildProductIndex' => 'Product Reindex (deprecated)',
        'rebuildStoreAdditionalSectionsIndex' => 'Additional Section Reindex (deprecated)',
        'rebuildStoreSuggestionIndex' => 'Suggestion Reindex (deprecated)',
        'rebuildStorePageIndex' => 'Page Reindex (deprecated)',
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
