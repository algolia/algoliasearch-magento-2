<?php

namespace Algolia\AlgoliaSearch\Ui\Component\Listing\Column;

use Algolia\AlgoliaSearch\Api\Data\JobInterface;

class Status extends \Magento\Ui\Component\Listing\Columns\Column
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
            $item[$fieldName] = $this->defineStatus($item);
        }

        return $dataSource;
    }

    /**
     * @param array $item
     *
     * @return string
     */
    private function defineStatus($item)
    {
        $status = JobInterface::STATUS_PROCESSING;

        if (is_null($item[JobInterface::FIELD_PID])) {
            $status = JobInterface::STATUS_NEW;
        }

        if ((int) $item[JobInterface::FIELD_RETRIES] >= $item[JobInterface::FIELD_MAX_RETRIES]) {
            $status = JobInterface::STATUS_ERROR;
        }

        return $status;
    }
}
