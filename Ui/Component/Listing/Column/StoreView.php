<?php

namespace Algolia\AlgoliaSearch\Ui\Component\Listing\Column;

use Magento\Search\Ui\Component\Listing\Column\StoreView as NativeStoreView;

class StoreView extends NativeStoreView
{
    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $item['store_id_num'] = $item['store_id'];
                $item[$this->getData('name')] = $this->prepareItem($item);
            }
        }

        return $dataSource;
    }
}
