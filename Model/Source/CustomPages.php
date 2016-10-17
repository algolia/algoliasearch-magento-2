<?php

namespace Algolia\AlgoliaSearch\Model\Source;

use Magento\Framework\App\ObjectManager;

class CustomPages extends AbstractTable
{
    protected function getTableData()
    {
        $objectManager = ObjectManager::getInstance();
        $pageCollection = $objectManager->create('Magento\Cms\Model\ResourceModel\Page\Collection');

        return [
            'attribute' => [
                'label'  => 'Page',
                'values' => function () use ($pageCollection) {
                    $options = [];
                    $magento_pages = $pageCollection->addFieldToFilter('is_active', 1);

                    foreach ($magento_pages as $page) {
                        $options[$page->getData('identifier')] = $page->getData('identifier');
                    }

                    return $options;
                },
            ],
        ];
    }
}
