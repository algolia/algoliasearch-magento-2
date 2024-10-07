<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Model\Indexer\Product as ProductIndexer;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Test\Integration\IndexingTestCase;

class ReplicaIndexingTest extends IndexingTestCase
{
    protected ?ReplicaManagerInterface $replicaManager = null;
    protected ?ProductIndexer $productIndexer = null;

    protected ?IndicesConfigurator $indicesConfigurator = null;

    protected ?string $indexSuffix = null;

    protected ?array $ogSortingState = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->productIndexer = $this->objectManager->get(ProductIndexer::class);
        $this->replicaManager = $this->objectManager->get(ReplicaManagerInterface::class);
        $this->indicesConfigurator = $this->objectManager->get(IndicesConfigurator::class);
        $this->indexSuffix = 'products';

        $this->ogSortingState = $this->configHelper->getSorting();
    }

    protected function getIndexName(string $storeIndexPart): string
    {
        return $this->indexPrefix . $storeIndexPart . $this->indexSuffix;
    }

    public function processFullReindexProducts(): void
    {
        $this->processFullReindex($this->productIndexer, $this->indexSuffix);
    }

    protected function hasSortingAttribute($sortAttr, $sortDir): bool
    {
        $sorting = $this->configHelper->getSorting();
        return (bool) array_filter(
            $sorting,
            function($sort) use ($sortAttr, $sortDir) {
                return $sort['attribute'] == $sortAttr
                    && $sort['sort'] == $sortDir;
            }
        );
    }

    protected function assertSortingAttribute($sortAttr, $sortDir): void
    {
        $this->assertTrue($this->hasSortingAttribute($sortAttr, $sortDir));
    }

    protected function assertNoSortingAttribute($sortAttr, $sortDir): void
    {
        $this->assertFalse($this->hasSortingAttribute($sortAttr, $sortDir));
    }

    public function testReplicaLimits()
    {
        $this->assertEquals(20, $this->replicaManager->getMaxVirtualReplicasPerIndex());
    }

    /**
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     */
    public function testStandardReplicaConfig(): void
    {
        $sortAttr = 'created_at';
        $sortDir = 'desc';
        $this->assertSortingAttribute($sortAttr, $sortDir);

        $this->indicesConfigurator->saveConfigurationToAlgolia(1);
        $this->algoliaHelper->waitLastTask();

        // Assert replica config created
        $indexName = $this->getIndexName('default_');
        $currentSettings = $this->algoliaHelper->getSettings($indexName);
        $this->assertArrayHasKey('replicas', $currentSettings);

        $sortIndexName = $indexName . '_' . $sortAttr . '_' . $sortDir;

        $this->assertTrue($this->isStandardReplica($currentSettings['replicas'], $sortIndexName));
        $this->assertFalse($this->isVirtualReplica($currentSettings['replicas'], $sortIndexName));

        // Assert replica index created
        $replicaSettings = $this->algoliaHelper->getSettings($sortIndexName);
        $this->assertArrayHasKey('primary', $replicaSettings);
        $this->assertEquals($indexName, $replicaSettings['primary']);

        // Assert standard replica ranking config
        $this->assertArrayHasKey('ranking', $replicaSettings);
        $this->assertContains("$sortDir($sortAttr)", $replicaSettings['ranking']);

    }

    /**
     * @magentoDbIsolation disabled
     * @group virtual
     */
    public function testVirtualReplicaConfig(): void
    {
        $indexName = $this->getIndexName('default_');
        $ogAlgoliaSettings = $this->algoliaHelper->getSettings($indexName);

        $this->assertFalse(array_key_exists('replicas', $ogAlgoliaSettings));

        $productHelper = $this->objectManager->get(ProductHelper::class);
        $sortAttr = 'color';
        $sortDir = 'asc';
        $attributes = $productHelper->getAllAttributes();
        $this->assertArrayHasKey($sortAttr, $attributes);

        $this->assertNoSortingAttribute($sortAttr, $sortDir);

        $sorting = $this->configHelper->getSorting();
        $sorting[] = [
            'attribute'      => $sortAttr,
            'sort'           => $sortDir,
            'sortLabel'      => $sortAttr,
            'virtualReplica' => 1
        ];
        $this->configHelper->setSorting($sorting);

        $this->assertConfigInDb('algoliasearch_instant/instant_sorts/sorts', json_encode($sorting));

        $this->refreshConfigFromDb();

        $this->assertSortingAttribute($sortAttr, $sortDir);

        // Cannot use config fixture because we have disabled db isolation
        $this->setConfig('algoliasearch_instant/instant/is_instant_enabled', 1);

        $this->indicesConfigurator->saveConfigurationToAlgolia(1);
        $this->algoliaHelper->waitLastTask();

        // Assert replica config created
        $currentSettings = $this->algoliaHelper->getSettings($indexName);
        $this->assertArrayHasKey('replicas', $currentSettings);

        $sortIndexName = $indexName . '_' . $sortAttr . '_' . $sortDir;

        $this->assertTrue($this->isVirtualReplica($currentSettings['replicas'], $sortIndexName));
        $this->assertFalse($this->isStandardReplica($currentSettings['replicas'], $sortIndexName));

        // Assert replica index created
        $replicaSettings = $this->algoliaHelper->getSettings($sortIndexName);
        $this->assertArrayHasKey('primary', $replicaSettings);
        $this->assertEquals($indexName, $replicaSettings['primary']);

        // Restore prior state (for this test only)
        $this->algoliaHelper->setSettings($indexName, $ogAlgoliaSettings);
        $this->setConfig('algoliasearch_instant/instant/is_instant_enabled', 0);

    }

    /**
     * @param string[] $replicaSetting
     * @param string $replicaIndexName
     * @return bool
     */
    protected function isVirtualReplica(array $replicaSetting, string $replicaIndexName): bool
    {
        return (bool) array_filter(
            $replicaSetting,
            function ($replica) use ($replicaIndexName) {
                return str_contains($replica, "virtual($replicaIndexName)");
            }
        );
    }

    protected function isStandardReplica(array $replicaSetting, string $replicaIndexName): bool
    {
        return (bool) array_filter(
            $replicaSetting,
            function ($replica) use ($replicaIndexName) {
                $regex = '/^' . preg_quote($replicaIndexName) . '$/';
                return preg_match($regex, $replica);
            }
        );
    }

    public function tearDown(): void
    {
        $this->configHelper->setSorting($this->ogSortingState);
    }
}
