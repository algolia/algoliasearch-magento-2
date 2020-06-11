<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\Data;

class IndexMover
{
    /** @var Data */
    private $baseHelper;

    /** @var AlgoliaHelper */
    private $algoliaHelper;

    /** @var IndicesConfigurator */
    private $indicesConfigurator;

    /**
     * @param Data $baseHelper
     * @param AlgoliaHelper $algoliaHelper
     * @param IndicesConfigurator $indicesConfigurator
     */
    public function __construct(
        Data $baseHelper,
        AlgoliaHelper $algoliaHelper,
        IndicesConfigurator $indicesConfigurator
    ) {
        $this->baseHelper = $baseHelper;
        $this->algoliaHelper = $algoliaHelper;
        $this->indicesConfigurator = $indicesConfigurator;
    }

    /**
     * @param string $tmpIndexName
     * @param string $indexName
     */
    public function moveIndex($tmpIndexName, $indexName)
    {
        if ($this->baseHelper->isIndexingEnabled() === false) {
            return;
        }

        $this->algoliaHelper->moveIndex($tmpIndexName, $indexName);
    }

    /**
     * @param string $tmpIndexName
     * @param string $indexName
     * @param int $storeId
     *
     * @throws AlgoliaException
     */
    public function moveIndexWithSetSettings($tmpIndexName, $indexName, $storeId)
    {
        if ($this->baseHelper->isIndexingEnabled() === false) {
            return;
        }

        $this->indicesConfigurator->saveConfigurationToAlgolia($storeId, true);
        $this->moveIndex($tmpIndexName, $indexName);
    }
}
