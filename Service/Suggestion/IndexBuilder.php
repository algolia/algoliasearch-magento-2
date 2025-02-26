<?php

namespace Algolia\AlgoliaSearch\Service\Suggestion;

use Algolia\AlgoliaSearch\Api\Builder\IndexBuilderInterface;
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

class IndexBuilder extends AbstractIndexBuilder implements IndexBuilderInterface
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
     * @param array|null $options
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     */
    public function buildIndexFull(int $storeId, array $options = null): void
    {
        $this->buildIndex($storeId, null, null);
    }

    /**
     * @param int $storeId
     * @param array|null $entityIds
     * @param array|null $options
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     */
    public function buildIndex(int $storeId, ?array $entityIds, ?array $options): void
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
            $this->saveObjects($indexData, $indexName, $storeId);
        }

        unset($indexData);
        $collection->walk('clearInstance');
        $collection->clear();
        unset($collection);
    }

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws ExceededRetriesException
     */
    protected function moveStoreSuggestionIndex(int $storeId): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }
        $tmpIndexName = $this->suggestionHelper->getTempIndexName($storeId);
        $indexName = $this->suggestionHelper->getIndexName($storeId);
        $this->algoliaHelper->copyQueryRules($indexName, $tmpIndexName, $storeId);
        $this->algoliaHelper->moveIndex($tmpIndexName, $indexName, $storeId);
    }
}
