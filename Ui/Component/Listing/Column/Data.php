<?php

namespace Algolia\AlgoliaSearch\Ui\Component\Listing\Column;

class Data extends \Magento\Ui\Component\Listing\Columns\Column
{

    const MAX_DEPTH_DISPLAY = 3;

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
            $item[$fieldName] = is_array($data) ? $this->formatData($data) : '';;
        }

        return $dataSource;
    }

    protected function formatData(array $data, $depth = 0): string
    {
        if ($depth > self::MAX_DEPTH_DISPLAY) {
            return '';
        }

        $formattedData = '';

        foreach ($data as $key => $value) {
            $stringKey = '- <strong>' . $key . '</strong>';

            if (is_array($value)) {
                if ($key === 'entityIds') {
                    $formattedData .=
                        str_repeat('&nbsp;&nbsp;&nbsp;', $depth ) . $stringKey . ' : '  . implode(', ', $value) . '<br>';
                } else {
                    $formattedData .= $stringKey . ' :<br>'  . $this->formatData($value, ++$depth);
                }

                continue;
            }

            $formattedData .= str_repeat('&nbsp;&nbsp;&nbsp;', $depth ) . $stringKey . ' : ' . $value . '<br>';
        }

        return $formattedData;
    }
}
