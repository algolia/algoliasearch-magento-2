<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Eav\Model\Config;
use Magento\Framework\DataObject;

class AdditionalSectionHelper
{
    private $eventManager;

    private $objectManager;

    private $eavConfig;

    public function __construct(
        ManagerInterface $eventManager,
        ObjectManagerInterface $objectManager,
        Config $eavConfig
    ) {
        $this->eventManager = $eventManager;
        $this->objectManager = $objectManager;
        $this->eavConfig = $eavConfig;
    }

    public function getIndexNameSuffix()
    {
        return '_section';
    }

    public function getIndexSettings($storeId)
    {
        $indexSettings = [
            'searchableAttributes' => ['unordered(value)'],
        ];

        $transport = new DataObject($indexSettings);
        $this->eventManager->dispatch(
            'algolia_additional_sections_index_before_set_settings',
            ['store_id' => $storeId, 'index_settings' => $transport]
        );
        $indexSettings = $transport->getData();

        return $indexSettings;
    }

    public function getAttributeValues($storeId, $section)
    {
        $attributeCode = $section['name'];

        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $products */
        $products = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Product\Collection');
        $products = $products->addStoreFilter($storeId)
            ->addAttributeToFilter($attributeCode, ['notnull' => true])
            ->addAttributeToFilter($attributeCode, ['neq' => ''])
            ->addAttributeToSelect($attributeCode);

        $usedAttributeValues = array_unique($products->getColumnValues($attributeCode));

        $attributeModel = $this->eavConfig->getAttribute('catalog_product', $attributeCode)->setStoreId($storeId);

        $values = $attributeModel->getSource()->getOptionText(
            implode(',', $usedAttributeValues)
        );

        if (!$values || count($values) === 0) {
            $values = array_unique($products->getColumnValues($attributeCode));
        }

        if ($values && is_array($values) === false) {
            $values = [$values];
        }

        $values = array_map(function ($value) use ($section, $storeId) {
            $record = [
                'objectID' => $value,
                'value'    => $value,
            ];

            $transport = new DataObject($record);
            $this->eventManager->dispatch(
                'algolia_additional_section_item_index_before',
                ['section' => $section, 'record' => $transport, 'store_id' => $storeId]
            );
            $this->eventManager->dispatch(
                'algolia_additional_section_items_before_index',
                ['section' => $section, 'record' => $transport, 'store_id' => $storeId]
            );
            $record = $transport->getData();

            return $record;
        }, $values);

        return $values;
    }
}
