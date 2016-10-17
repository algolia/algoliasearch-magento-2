<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Magento\Framework\DataObject;

class AdditionalSectionHelper extends BaseHelper
{
    protected function getIndexNameSuffix()
    {
        return '_section';
    }

    public function getIndexSettings($storeId)
    {
        return [
            'attributesToIndex' => ['value'],
        ];
    }

    public function getAttributeValues($storeId, $section)
    {
        $attributeCode = $section['name'];

        /** @var $products \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection */
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

        if (!$values || count($values) == 0) {
            $values = array_unique($products->getColumnValues($attributeCode));
        }

        if ($values && is_array($values) == false) {
            $values = [$values];
        }

        $values = array_map(function ($value) use ($section) {

            $record = [
                'objectID' => $value,
                'value'    => $value,
            ];

            $transport = new DataObject($record);

            $this->eventManager->dispatch('algolia_additional_section_item_index_before', ['section' => $section, 'record' => $transport]);

            $record = $transport->getData();

            return $record;
        }, $values);

        return $values;
    }
}
