<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Magento\Search\Model\Query;

class SuggestionHelper extends BaseHelper
{
    protected function getIndexNameSuffix()
    {
        return '_suggestions';
    }

    public function getIndexSettings($storeId)
    {
        return [
            'attributesToIndex'    => ['query'],
            'customRanking'        => ['desc(popularity)', 'desc(number_of_results)', 'asc(date)'],
            'typoTolerance'        => false,
            'attributesToRetrieve' => ['query'],
        ];
    }

    public function getObject(Query $suggestion)
    {
        $suggestion_obj = [
            'objectID'          => $suggestion->getData('query_id'),
            'query'             => $suggestion->getData('query_text'),
            'number_of_results' => (int) $suggestion->getData('num_results'),
            'popularity'        => (int) $suggestion->getData('popularity'),
            'updated_at'        => (int) strtotime($suggestion->getData('updated_at')),
        ];

        return $suggestion_obj;
    }

    public function getPopularQueries($storeId)
    {
        $collection = $this->objectManager->create('\Magento\Search\Model\ResourceModel\Query\Collection');
        $collection->getSelect()->where('num_results >= ' . $this->config->getMinNumberOfResults() . ' AND popularity >= ' . $this->config->getMinPopularity() . ' AND query_text != "__empty__"');
        $collection->getSelect()->limit(12);
        $collection->setOrder('popularity', 'DESC');
        $collection->setOrder('num_results', 'DESC');
        $collection->setOrder('updated_at', 'ASC');

        if ($storeId) {
            $collection->getSelect()->where('store_id = ?', (int) $storeId);
        }

        $collection->load();

        $suggestions = [];

        foreach ($collection as $suggestion) {
            if (strlen($suggestion['query_text']) >= 3) {
                $suggestions[] = $suggestion['query_text'];
            }
        }

        return array_slice($suggestions, 0, 9);
    }

    public function getSuggestionCollectionQuery($storeId)
    {
        /** @var \Magento\Search\Model\ResourceModel\Query\Collection $collection */
        $collection = $this->objectManager->create('\Magento\Search\Model\ResourceModel\Query\Collection');
        $collection = $collection->addStoreFilter($storeId)->setStoreId($storeId);

        $collection->getSelect()->where('num_results >= ' . $this->config->getMinNumberOfResults($storeId) . ' AND popularity >= ' . $this->config->getMinPopularity($storeId) . ' AND query_text != "__empty__"');

        return $collection;
    }
}
