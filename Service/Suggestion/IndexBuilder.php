<?php

namespace Algolia\AlgoliaSearch\Service\Suggestion;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\AbstractIndexBuilder;
use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Search\Model\Query;
use Magento\Search\Model\ResourceModel\Query\Collection as QueryCollection;
use Magento\Store\Model\App\Emulation;

class IndexBuilder extends AbstractIndexBuilder
{
    public function __construct(
        protected ConfigHelper      $configHelper,
        protected DiagnosticsLogger $logger,
        protected Emulation         $emulation,
        protected ScopeCodeResolver $scopeCodeResolver,
        protected AlgoliaHelper     $algoliaHelper,
        protected SuggestionHelper  $suggestionHelper
    ){
        parent::__construct($configHelper, $logger, $emulation, $scopeCodeResolver, $algoliaHelper);
    }

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     */
    public function buildIndex(int $storeId): void
    {
        if ($this->isIndexingEnabled($storeId) === false || !$this->configHelper->isQuerySuggestionsIndexEnabled($storeId)) {
            return;
        }

        if (!$this->configHelper->isQuerySuggestionsIndexEnabled($storeId)) {
            $this->logger->log('Query Suggestions Indexing is not enabled for the store.');
            return;
        }

        $collection = $this->suggestionHelper->getSuggestionCollectionQuery($storeId);
        $size = $collection->getSize();

        if ($size > 0) {
            $pages = ceil($size / $this->configHelper->getNumberOfElementByPage());
            $collection->clear();
            $page = 1;

            while ($page <= $pages) {
                $this->rebuildStoreSuggestionIndexPage(
                    $storeId,
                    $collection,
                    $page,
                    $this->configHelper->getNumberOfElementByPage()
                );
                $page++;
            }
            unset($indexData);
        }
        $this->moveStoreSuggestionIndex($storeId);
    }

    /**
     * @param int $storeId
     * @param QueryCollection $collectionDefault
     * @param int $page
     * @param int $pageSize
     * @return void
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    protected function rebuildStoreSuggestionIndexPage(int $storeId, QueryCollection $collectionDefault, int $page, int $pageSize): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->algoliaHelper->setStoreId($storeId);
        $collection = clone $collectionDefault;
        $collection->setCurPage($page)->setPageSize($pageSize);
        $collection->load();
        $indexName = $this->suggestionHelper->getTempIndexName($storeId);
        $indexData = [];

        /** @var Query $suggestion */
        foreach ($collection as $suggestion) {
            $suggestion->setStoreId($storeId);
            $suggestionObject = $this->suggestionHelper->getObject($suggestion);
            if (mb_strlen($suggestionObject['query']) >= 3) {
                array_push($indexData, $suggestionObject);
            }
        }
        if (count($indexData) > 0) {
            $this->saveObjects($indexData, $indexName);
        }

        unset($indexData);
        $collection->walk('clearInstance');
        $collection->clear();
        $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);
        unset($collection);
    }

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws ExceededRetriesException
     */
    public function moveStoreSuggestionIndex(int $storeId): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }
        $this->algoliaHelper->setStoreId($storeId);
        $tmpIndexName = $this->suggestionHelper->getTempIndexName($storeId);
        $indexName = $this->suggestionHelper->getIndexName($storeId);
        $this->algoliaHelper->copyQueryRules($indexName, $tmpIndexName);
        $this->algoliaHelper->moveIndex($tmpIndexName, $indexName);
        $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);
    }
}
