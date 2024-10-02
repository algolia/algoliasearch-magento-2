<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Model\Indexer\Product as ProductIndexer;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Test\Integration\IndexingTestCase;

class ReplicaIndexingTest extends IndexingTestCase
{
    protected ?ReplicaManagerInterface $replicaManager;
    protected ?ProductIndexer $productIndexer;

    protected ?IndicesConfigurator $indicesConfigurator;

    protected ?string $indexSuffix;

    public function setUp(): void
    {
        parent::setUp();
        $this->productIndexer = $this->objectManager->get(ProductIndexer::class);
        $this->replicaManager = $this->objectManager->get(ReplicaManagerInterface::class);
        $this->indicesConfigurator = $this->getObjectManager()->get(IndicesConfigurator::class);
        $this->indexSuffix = 'products';

        // Replicas will not get created if InstantSearch is not used
        $this->setConfig('algoliasearch_instant/instant/is_instant_enabled', 1);
    }

    protected function getIndexName(string $storeIndexPart): string
    {
        return $this->indexPrefix . $storeIndexPart . $this->indexSuffix;
    }

    public function processFullReindexProducts(): void
    {
        $this->processFullReindex($this->productIndexer, $this->indexSuffix);
    }

    public function testReplicaConfig(): void
    {
        $sorting = $this->configHelper->getSorting();
        $sortAttr = 'created_at';
        $sortDir = 'desc';

        // Has created_at sort
        $this->assertTrue(
            (bool) array_filter(
                $sorting,
                function($sort) use ($sortAttr, $sortDir) {
                    return $sort['attribute'] == $sortAttr
                        && $sort['sort'] == $sortDir;
                }
            )
        );

        // Expected replica max
        $this->assertEquals(20, $this->replicaManager->getMaxVirtualReplicasPerIndex());

        $this->indicesConfigurator->saveConfigurationToAlgolia(1);
        $this->algoliaHelper->waitLastTask();

        // Assert replica config created
        $indexName = $this->getIndexName('default_');
        $currentSettings = $this->algoliaHelper->getSettings($indexName);
        $this->assertArrayHasKey('replicas', $currentSettings);

        $sortIndexName = $indexName . '_' . $sortAttr . '_' . $sortDir;

        $this->assertTrue(
            (bool) array_filter(
                $currentSettings['replicas'],
                function($replica) use ($sortIndexName) {
                    return str_contains($replica, $sortIndexName);
                }
            )
        );

        // Assert replica index created
        $replicaSettings = $this->algoliaHelper->getSettings($sortIndexName);
        $this->assertArrayHasKey('primary', $replicaSettings);
        $this->assertEquals($indexName, $replicaSettings['primary']);

        // Assert standard replica ranking config
        $this->assertArrayHasKey('ranking', $replicaSettings);
        $this->assertContains("$sortDir($sortAttr)", $replicaSettings['ranking']);

    }

    public function tearDown(): void
    {
        $this->setConfig('algoliasearch_instant/instant/is_instant_enabled', 0);
    }
}
