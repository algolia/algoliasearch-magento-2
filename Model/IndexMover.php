<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Magento\Framework\Exception\NoSuchEntityException;

class IndexMover
{
    public function __construct(
        protected Data $baseHelper,
        protected AlgoliaConnector $algoliaConnector,
        protected IndexOptionsBuilder $indexOptionsBuilder,
        protected IndicesConfigurator $indicesConfigurator
    ){}

    /**
     * @param string $tmpIndexName
     * @param string $indexName
     * @param int $storeId
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function moveIndex(string $tmpIndexName, string $indexName, int $storeId): void
    {
        if ($this->baseHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        $fromIndexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($tmpIndexName, $storeId);
        $toIndexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->algoliaConnector->moveIndex($fromIndexOptions, $toIndexOptions);
    }

    /**
     * @param string $tmpIndexName
     * @param string $indexName
     * @param int $storeId
     *
     * @throws AlgoliaException
     */
    public function moveIndexWithSetSettings(string $tmpIndexName, string $indexName, int $storeId): void
    {
        if ($this->baseHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->indicesConfigurator->saveConfigurationToAlgolia($storeId, true);
        $this->moveIndex($tmpIndexName, $indexName, $storeId);
    }
}
