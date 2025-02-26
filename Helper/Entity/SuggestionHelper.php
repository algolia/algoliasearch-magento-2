<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Suggestion\RecordBuilder as SuggestionRecordBuilder;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Search\Model\Query;
use Magento\Search\Model\ResourceModel\Query\Collection as QueryCollection;
use Magento\Search\Model\ResourceModel\Query\CollectionFactory as QueryCollectionFactory;

class SuggestionHelper extends AbstractEntityHelper
{
    use EntityHelperTrait;
    public const INDEX_NAME_SUFFIX = '_suggestions';

    /**
     * @var string
     */
    public const POPULAR_QUERIES_CACHE_TAG = 'algoliasearch_popular_queries_cache_tag';

    public function __construct(
        protected ManagerInterface        $eventManager,
        protected QueryCollectionFactory  $queryCollectionFactory,
        protected ConfigCache             $cache,
        protected ConfigHelper            $configHelper,
        protected SerializerInterface     $serializer,
        protected SuggestionRecordBuilder $suggestionRecordBuilder,
        protected IndexNameFetcher        $indexNameFetcher,
    ) {
        parent::__construct($indexNameFetcher);
    }

    /**
     * @param int|null $storeId
     * @return array
     */
    public function getIndexSettings(?int $storeId = null): array
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
        return $transport->getData();
    }

    /**
     * @param Query $suggestion
     * @return array
     *
     * @deprecated (will be removed in v3.16.0)
     */
    public function getObject(Query $suggestion)
    {
        return $this->suggestionRecordBuilder->buildRecord($suggestion);
    }

    /**
     * @param $storeId
     * @return array|bool|float|int|string|null
     */
    public function getPopularQueries($storeId = null)
    {
        if (!$this->configHelper->isInstantEnabled($storeId) || !$this->configHelper->showSuggestionsOnNoResultsPage($storeId)) {
            return [];
        }
        $queries = $this->cache->load(self::POPULAR_QUERIES_CACHE_TAG . '_' . $storeId);
        if ($queries !== false) {
            return $this->serializer->unserialize($queries);
        }

        /** @var QueryCollection $collection */
        $collection = $this->queryCollectionFactory->create();
        $collection->getSelect()->where(
            'num_results >= ' . $this->configHelper->getMinNumberOfResults() . '
            AND popularity >= ' . $this->configHelper->getMinPopularity() . '
            AND query_text != "__empty__" AND CHAR_LENGTH(query_text) >= 3'
        );

        if ($storeId) {
            $collection->getSelect()->where('store_id = ?', (int) $storeId);
        }

        $collection->setOrder('popularity', 'DESC');
        $collection->setOrder('num_results', 'DESC');
        $collection->setOrder('updated_at', 'ASC');

        $collection->getSelect()->limit(10);

        $queries = $collection->getColumnValues('query_text');

        $this->cache->save(
            $this->serializer->serialize($queries),
            self::POPULAR_QUERIES_CACHE_TAG . '_' . $storeId,
            [],
            $this->configHelper->getCacheTime($storeId)
        );

        return $queries;
    }

    /**
     * @param int $storeId
     * @return QueryCollection
     */
    public function getSuggestionCollectionQuery(int $storeId): QueryCollection
    {
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
