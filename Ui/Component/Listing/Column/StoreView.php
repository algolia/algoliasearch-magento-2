<?php

namespace Algolia\AlgoliaSearch\Ui\Component\Listing\Column;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Search\Ui\Component\Listing\Column\StoreView as NativeStoreView;

class StoreView extends NativeStoreView
{
    /** Data-source key holding the raw store id a row arrives with, before any rendering. */
    public const string STORE_ID = 'store_id';

    /**
     * Data-source key under which the raw numeric store id survives the label overwrite
     * in prepareDataSource(), for consumers that need the id rather than the label.
     */
    public const string STORE_ID_NUM = 'store_id_num';

    /**
     * Prepare Data Source
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                // Stash the raw numeric id before the rendered label replaces 'store_id'
                // LandingPageActions and QueryActions read 'store_id_num' to scope their frontend URLs.
                $item[self::STORE_ID_NUM] = $item[self::STORE_ID];
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
        if (!isset($item[self::STORE_ID])) {
            return '';
        }

        try {
            return parent::prepareItem($item);
        } catch (NoSuchEntityException) {
            return __('Store ID: %1 (deleted)', $item[self::STORE_ID]);
        }
    }
}
