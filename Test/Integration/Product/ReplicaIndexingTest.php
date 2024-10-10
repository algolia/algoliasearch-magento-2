<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Model\Indexer\Product as ProductIndexer;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;

class ReplicaIndexingTest extends TestCase
{
    protected ?ReplicaManagerInterface $replicaManager = null;
    protected ?ProductIndexer $productIndexer = null;

    protected ?IndicesConfigurator $indicesConfigurator = null;

    protected ?string $indexName = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productIndexer = $this->objectManager->get(ProductIndexer::class);
        $this->replicaManager = $this->objectManager->get(ReplicaManagerInterface::class);
        $this->indicesConfigurator = $this->objectManager->get(IndicesConfigurator::class);
        $this->indexSuffix = 'products';
        $this->indexName = $this->getIndexName('default');
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
        $primaryIndexName = $this->indexName;
        $currentSettings = $this->algoliaHelper->getSettings($primaryIndexName);
        $this->assertArrayHasKey('replicas', $currentSettings);

        $replicaIndexName = $primaryIndexName . '_' . $sortAttr . '_' . $sortDir;

        $this->assertTrue($this->isStandardReplica($currentSettings['replicas'], $replicaIndexName));
        $this->assertFalse($this->isVirtualReplica($currentSettings['replicas'], $replicaIndexName));

        $replicaSettings = $this->assertReplicaIndexExists($primaryIndexName, $replicaIndexName);
        $this->assertStandardReplicaRanking($replicaSettings, "$sortDir($sortAttr)");
    }

    /**
     * This test involves verifying modifications in the database
     * so it must be responsible for its own set up and tear down
     * @magentoDbIsolation disabled
     * @group virtual
     */
    public function testVirtualReplicaConfig(): void
    {
        $primaryIndexName = $this->getIndexName('default');
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
        $currentSettings = $this->algoliaHelper->getSettings($primaryIndexName);
        $this->assertArrayHasKey('replicas', $currentSettings);

        $replicaIndexName = $primaryIndexName . '_' . $sortAttr . '_' . $sortDir;

        $this->assertTrue($this->isVirtualReplica($currentSettings['replicas'], $replicaIndexName));
        $this->assertFalse($this->isStandardReplica($currentSettings['replicas'], $replicaIndexName));

        // Assert replica index created
        $replicaSettings = $this->assertReplicaIndexExists($primaryIndexName, $replicaIndexName);
        $this->assertVirtualReplicaRanking($replicaSettings, "$sortDir($sortAttr)");

        // Restore prior state (for this test only)
        $this->configHelper->setSorting($ogSortingState);
        $this->setConfig(ConfigHelper::IS_INSTANT_ENABLED, 0);
    }

    /**
     * ConfigHelper::setSorting uses WriterInterface which does not update unless DB isolation is disabled
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
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws \ReflectionException
     */
    public function testReplicaRebuild(): void
    {
        $primaryIndexName = $this->getIndexName('default');

        $this->mockSortUpdate('price', 'desc', ['virtualReplica' => 1]);
        $sorting = $this->objectManager->get(\Algolia\AlgoliaSearch\Service\Product\SortingTransformer::class)->getSortingIndices(1, null, null, true);

        $syncCmd = $this->objectManager->get(\Algolia\AlgoliaSearch\Console\Command\ReplicaSyncCommand::class);
        $this->mockProperty($syncCmd, 'output', \Symfony\Component\Console\Output\OutputInterface::class);
        $syncCmd->syncReplicas();
        $this->algoliaHelper->waitLastTask();

        $rebuildCmd = $this->objectManager->get(\Algolia\AlgoliaSearch\Console\Command\ReplicaRebuildCommand::class);
        $this->invokeMethod(
            $rebuildCmd,
            'execute',
            [
                $this->createMock(\Symfony\Component\Console\Input\InputInterface::class),
                $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class)
            ]
        );
        $this->algoliaHelper->waitLastTask();

        $currentSettings = $this->algoliaHelper->getSettings($primaryIndexName);
        $this->assertArrayHasKey('replicas', $currentSettings);
        $replicas = $currentSettings['replicas'];

        $this->assertEquals(count($sorting), count($replicas));
        $this->assertSortToReplicaConfigParity($primaryIndexName, $sorting, $replicas);
    }

    /**
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws \ReflectionException
     */
    public function testReplicaSync(): void
    {
        $primaryIndexName = $this->getIndexName('default');
        $this->mockSortUpdate('created_at', 'desc', ['virtualReplica' => 1]);

        $sorting = $this->objectManager->get(\Algolia\AlgoliaSearch\Service\Product\SortingTransformer::class)->getSortingIndices(1, null, null, true);

        $cmd = $this->objectManager->get(\Algolia\AlgoliaSearch\Console\Command\ReplicaSyncCommand::class);

        $this->mockProperty($cmd, 'output', \Symfony\Component\Console\Output\OutputInterface::class);

        $cmd->syncReplicas();
        $this->algoliaHelper->waitLastTask();

        $currentSettings = $this->algoliaHelper->getSettings($primaryIndexName);
        $this->assertArrayHasKey('replicas', $currentSettings);
        $replicas = $currentSettings['replicas'];

        $this->assertEquals(count($sorting), count($replicas));
        $this->assertSortToReplicaConfigParity($primaryIndexName, $sorting, $replicas);
    }

    protected function assertSortToReplicaConfigParity(string $primaryIndexName, array $sorting, array $replicas): void
    {
        foreach ($sorting as $sortAttr) {
            $replicaIndexName = $sortAttr['name'];
            $isVirtual = array_key_exists('virtualReplica', $sortAttr) && $sortAttr['virtualReplica'];
            $needle = $isVirtual
                ? "virtual($replicaIndexName)"
                : $replicaIndexName;
            $this->assertContains($needle, $replicas);

            $replicaSettings = $this->assertReplicaIndexExists($primaryIndexName, $replicaIndexName);
            $sort = reset($sortAttr['ranking']);
            if ($isVirtual) {
                $this->assertVirtualReplicaRanking($replicaSettings, $sort);
            } else {
                $this->assertStandardReplicaRanking($replicaSettings, $sort);
            }
        }
    }

    protected function assertReplicaIndexExists(string $primaryIndexName, string $replicaIndexName): array
    {
        $replicaSettings = $this->algoliaHelper->getSettings($replicaIndexName);
        $this->assertArrayHasKey('primary', $replicaSettings);
        $this->assertEquals($primaryIndexName, $replicaSettings['primary']);
        return $replicaSettings;
    }

    protected function assertReplicaRanking(array $replicaSettings, string $rankingKey, string $sort) {
        $this->assertArrayHasKey($rankingKey, $replicaSettings);
        $this->assertEquals($sort, reset($replicaSettings[$rankingKey]));
    }

    protected function assertStandardReplicaRanking(array $replicaSettings, string $sort): void
    {
        $this->assertReplicaRanking($replicaSettings, 'ranking', $sort);
    }

    protected function assertVirtualReplicaRanking(array $replicaSettings, string $sort): void
    {
        $this->assertReplicaRanking($replicaSettings, 'customRanking', $sort);
    }

    protected function assertStandardReplicaRankingOld(array $replicaSettings, string $sortAttr, string $sortDir): void
    {
        $this->assertArrayHasKey('ranking', $replicaSettings);
        $this->assertEquals("$sortDir($sortAttr)", array_shift($replicaSettings['ranking']));
    }

    protected function assertVirtualReplicaRankingOld(array $replicaSettings, string $sortAttr, string $sortDir): void
    {
        $this->assertArrayHasKey('customRanking', $replicaSettings);
        $this->assertEquals("$sortDir($sortAttr)", array_shift($replicaSettings['customRanking']));
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

}
