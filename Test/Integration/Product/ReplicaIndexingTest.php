<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
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

    public function setUp(): void
    {
        parent::setUp();
        $this->productIndexer = $this->objectManager->get(ProductIndexer::class);
        $this->replicaManager = $this->objectManager->get(ReplicaManagerInterface::class);
        $this->indicesConfigurator = $this->objectManager->get(IndicesConfigurator::class);
        $this->indexSuffix = 'products';
    }

    protected function getIndexName(string $storeIndexPart): string
    {
        return $this->indexPrefix . $storeIndexPart . $this->indexSuffix;
    }

    public function processFullReindexProducts(): void
    {
        $this->processFullReindex($this->productIndexer, $this->indexSuffix);
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
        $this->assertEquals("$sortDir($sortAttr)", array_shift($replicaSettings['ranking']));

    }

    /**
     * This test involves verifying modifications in the database
     * so it must be responsible for its own set up and tear down
     * @magentoDbIsolation disabled
     * @group virtual
     */
    public function testVirtualReplicaConfig(): void
    {
        $indexName = $this->getIndexName('default_');
        $ogSortingState = $this->configHelper->getSorting();

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

        $this->assertConfigInDb(ConfigHelper::SORTING_INDICES, json_encode($sorting));

        $this->refreshConfigFromDb();

        $this->assertSortingAttribute($sortAttr, $sortDir);

        // Cannot use config fixture because we have disabled db isolation
        $this->setConfig(ConfigHelper::IS_INSTANT_ENABLED, 1);

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

        // Assert virtual replica ranking config
        $this->assertArrayHasKey('customRanking', $replicaSettings);
        $this->assertEquals("$sortDir($sortAttr)", array_shift($replicaSettings['customRanking']));

        // Restore prior state (for this test only)
        $this->configHelper->setSorting($ogSortingState);
        $this->setConfig(ConfigHelper::IS_INSTANT_ENABLED, 0);
    }

    /**
     * ConfigHelper::setSorting used WriterInterface which does not update unless DB isolation is disabled
     * This provides a workaround to test using MutableScopeConfigInterface with DB isolation enabled
     */
    protected function mockSortUpdate(string $sortAttr, string $sortDir, array $attr): void
    {
        $sorting = $this->configHelper->getSorting();
        $existing = array_filter($sorting, function ($item) use ($sortAttr, $sortDir) {
           return $item['attribute'] === $sortAttr && $item['sort'] === $sortDir;
        });


        if ($existing) {
            $idx = array_key_first($existing);
            $sorting[$idx] = array_merge($existing[$idx], $attr);
        }
        else {
            $sorting[] = array_merge(
                [
                    'attribute' => $sortAttr,
                    'sort'       => $sortDir,
                    'sortLabel'  => $sortAttr
                ],
                $attr
            );
        }
        $this->setConfig(ConfigHelper::SORTING_INDICES, json_encode($sorting));
    }

    /**
     * @depends testReplicaSync
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     */
    public function testReplicaRebuild(): void
    {
        $indexName = $this->getIndexName('default_');

        $cmd = $this->objectManager->get(\Algolia\AlgoliaSearch\Console\Command\ReplicaRebuildCommand::class);
        $this->assertTrue(true);
    }

    /**
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     * @throws \ReflectionException
     */
    public function testReplicaSync(): void
    {
        $indexName = $this->getIndexName('default_');

        $this->mockSortUpdate('created_at', 'desc', ['virtualReplica' => 1]);

        $sorting = $this->objectManager->get(\Algolia\AlgoliaSearch\Service\Product\SortingTransformer::class)->getSortingIndices(1, null, null, true);

        $cmd = $this->objectManager->create(\Algolia\AlgoliaSearch\Console\Command\ReplicaSyncCommand::class);

        $this->mockProperty($cmd, 'output', \Symfony\Component\Console\Output\OutputInterface::class);

        $cmd->syncReplicas();

        $currentSettings = $this->algoliaHelper->getSettings($indexName);
        $this->assertArrayHasKey('replicas', $currentSettings);
        $replicas = $currentSettings['replicas'];

        $this->assertTrue(count($replicas) >= count($sorting));

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

    public function tearDown(): void
    {
        parent::tearDown();
    }

}
