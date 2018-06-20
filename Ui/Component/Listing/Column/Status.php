<?php

namespace Algolia\AlgoliaSearch\Ui\Component\Listing\Column;

class Status extends \Magento\Ui\Component\Listing\Columns\Column
{
    const STATUS_NEW = "new";
    const STATUS_PROCESSING = "processing";
    const STATUS_ERROR = "error";
    const STATUS_COMPLETE = "complete";

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
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
            $item[$fieldName] = $this->defineStatus($item);
        }

        return $dataSource;
    }


    /**
     * Define the job status
     *
     * @param array $item
     *
     * @return string
     */
    private function defineStatus($item)
    {
        $status = self::STATUS_PROCESSING;

        if (is_null($item['pid'])) {
            $status = self::STATUS_NEW;
        }

        if ((int) $item['retries'] >= $item['max_retries']) {
            $status = self::STATUS_ERROR;
        }

        return $status;
    }
}
