<?php

namespace Algolia\AlgoliaSearch\Ui\Component\Listing\Column;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Search\Ui\Component\Listing\Column\StoreView as NativeStoreView;

class StoreView extends NativeStoreView
{
    /**
     * Prepare Data Source
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                // Stash the raw numeric id before the rendered label replaces store_id below:
                // LandingPageActions and QueryActions read store_id_num to scope their frontend URLs.
                $item['store_id_num'] = $item['store_id'];
                $item[$this->getData('name')] = $this->prepareItem($item);
            }
        }

        return $dataSource;
    }

    /**
     * Get data
     *
     * @return string
     */
    protected function prepareItem(array $item)
    {
        // If a store-agnostic row is encountered do not label "All Store Views" (allow only for store ID = 0)
        if (!isset($item['store_id']) || $item['store_id'] === null) {
            return '';
        }

        try {
            return parent::prepareItem($item);
        } catch (NoSuchEntityException $e) {
            return __('Store ID: %1 (deleted)', $item['store_id']);
        }
    }
}
