<?php

namespace Algolia\AlgoliaSearch\Helper;

use Magento\Framework\DataObject;

class ProductDataArray extends DataObject
{
    public function getItems()
    {
        return $this->getData('items');
    }

    public function setItems(array $items)
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
        if (count($items) && isset($items[$productId])) {
            $keyValuePairs = array_merge($items[$productId], $keyValuePairs);
        }
        $items[$productId] = $keyValuePairs;
        $this->setItems($items);
    }

    public function getItem($productId)
    {
        $items = $this->getItems();

        return isset($items[$productId]) ? $items[$productId] : null;
    }
}
