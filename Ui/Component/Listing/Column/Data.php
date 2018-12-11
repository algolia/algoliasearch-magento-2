<?php

namespace Algolia\AlgoliaSearch\Ui\Component\Listing\Column;

class Data extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * @param array $dataSource
     *
     * @return array
     *
     * @since 101.0.0
     */
    public function prepareDataSource(array $dataSource)
    {
        $dataSource = parent::prepareDataSource($dataSource);

        if (empty($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $data = json_decode($item[$fieldName], true);
            $formattedData = '';
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                    $formattedData .= $key . ' : ' . $value . '<br>';
                }
            }
            $item[$fieldName] = $formattedData;
        }

        return $dataSource;
    }
}
