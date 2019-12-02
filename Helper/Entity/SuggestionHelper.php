<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Search\Model\Query;
use Magento\Search\Model\ResourceModel\Query\CollectionFactory as QueryCollectionFactory;

class SuggestionHelper
{
    private $eventManager;

    /**
     * @var QueryCollectionFactory
     */
    private $queryCollectionFactory;

    private $cache;

    private $configHelper;

    private $serializer;

    private $popularQueriesCacheId = 'algoliasearch_popular_queries_cache_tag';

    /**
     * SuggestionHelper constructor.
     *
     * @param ManagerInterface $eventManager
     * @param QueryCollectionFactory $queryCollectionFactory
     * @param ConfigCache $cache
     * @param ConfigHelper $configHelper
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ManagerInterface $eventManager,
        QueryCollectionFactory $queryCollectionFactory,
        ConfigCache $cache,
        ConfigHelper $configHelper,
        SerializerInterface $serializer
    ) {
        $this->eventManager = $eventManager;
        $this->queryCollectionFactory = $queryCollectionFactory;
        $this->cache = $cache;
        $this->configHelper = $configHelper;
        $this->serializer = $serializer;
    }

    public function getIndexNameSuffix()
    {
        return '_suggestions';
    }

    public function getIndexSettings($storeId)
    {
        $indexSettings = [
            'searchableAttributes' => ['query'],
            'customRanking'        => ['desc(popularity)', 'desc(number_of_results)', 'asc(date)'],
            'typoTolerance'        => false,
            'attributesToRetrieve' => ['query'],
        ];

        $transport = new DataObject($indexSettings);
        $this->eventManager->dispatch(
            'algolia_suggestions_index_before_set_settings',
            ['store_id' => $storeId, 'index_settings' => $transport]
        );
        $indexSettings = $transport->getData();

        return $indexSettings;
    }

    public function getObject(Query $suggestion)
    {
        $suggestionObject = [
            'objectID'          => $suggestion->getData('query_id'),
            'query'             => $suggestion->getData('query_text'),
            'number_of_results' => (int) $suggestion->getData('num_results'),
            'popularity'        => (int) $suggestion->getData('popularity'),
            'updated_at'        => (int) strtotime($suggestion->getData('updated_at')),
        ];

        $transport = new DataObject($suggestionObject);
        $this->eventManager->dispatch(
            'algolia_after_create_suggestion_object',
            ['suggestion' => $transport, 'suggestionObject' => $suggestion]
        );
        $suggestionObject = $transport->getData();

        return $suggestionObject;
    }

    public function getPopularQueries($storeId)
    {
        $queries = $this->cache->load($this->popularQueriesCacheId);
        if ($queries !== false) {
            return $this->serializer->unserialize($queries);
        }

        /** @var \Magento\Search\Model\ResourceModel\Query\Collection $collection */
        $collection = $this->queryCollectionFactory->create();
        $collection->getSelect()->where(
            'num_results >= ' . $this->configHelper->getMinNumberOfResults() . ' 
            AND popularity >= ' . $this->configHelper->getMinPopularity() . ' 
            AND query_text != "__empty__"'
        );
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
            if (mb_strlen($suggestion['query_text']) >= 3) {
                $suggestions[] = $suggestion['query_text'];
            }
        }

        $queries = array_slice($suggestions, 0, 9);

        $this->cache->save($this->serializer->serialize($queries), $this->popularQueriesCacheId, [], 24*3600);

        return $queries;
    }

    public function getSuggestionCollectionQuery($storeId)
    {
        /** @var \Magento\Search\Model\ResourceModel\Query\Collection $collection */
        $collection = $this->queryCollectionFactory->create()
            ->addStoreFilter($storeId)
            ->setStoreId($storeId);

        $collection->getSelect()->where(
            'num_results >= ' . $this->configHelper->getMinNumberOfResults($storeId) . ' 
            AND popularity >= ' . $this->configHelper->getMinPopularity($storeId) . ' 
            AND query_text != "__empty__"'
        );

        $this->eventManager->dispatch(
            'algolia_after_suggestions_collection_build',
            ['store' => $storeId, 'collection' => $collection]
        );

        return $collection;
    }
}
