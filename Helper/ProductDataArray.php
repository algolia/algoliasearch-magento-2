<?php

namespace Algolia\AlgoliaSearch\Helper;

use Magento\Framework\DataObject;

class ProductDataArray extends DataObject
{
    /**
     * @return array
     */
    public function getItems()
    {
        return $this->getData('items') ?: [];
    }

    /**
     * @param array $items
     */
    public function setItems($items = [])
    {
        $this->setData('items', $items);
    }

    /**
     * @param $productId
     * @param array $keyValuePairs
     */
    public function addProductData($productId, array $keyValuePairs)
    {
        $items = $this->getItems();
        if (is_array($items) && isset($items[$productId])) {
            $keyValuePairs = array_merge($items[$productId], $keyValuePairs);
        }
        $items[$productId] = $keyValuePairs;
        $this->setItems($items);
    }

    public function getItem($productId)
    {
        return $this->getData('items', $productId);
    }
}
