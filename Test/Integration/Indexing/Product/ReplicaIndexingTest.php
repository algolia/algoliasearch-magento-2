<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Product;

use Algolia\AlgoliaSearch\Api\LoggerInterface;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Registry\ReplicaState;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Product\ReplicaManager;
use Algolia\AlgoliaSearch\Service\Product\SortingTransformer;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\Traits\ReplicaAssertionsTrait;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;
use Algolia\AlgoliaSearch\Validator\VirtualReplicaValidatorFactory;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class ReplicaIndexingTest extends TestCase
{
    use ReplicaAssertionsTrait;

    protected ?ReplicaManagerInterface $replicaManager = null;

    protected ?IndicesConfigurator $indicesConfigurator = null;

    protected ?string $indexName = null;

    protected ?int $patchRetries = 3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->replicaManager = $this->objectManager->get(ReplicaManagerInterface::class);
        $this->indicesConfigurator = $this->objectManager->get(IndicesConfigurator::class);
        $this->indexOptionsBuilder = $this->objectManager->get(IndexOptionsBuilder::class);
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
        $this->algoliaConnector->waitLastTask();

        // Assert replica config created
        $primaryIndexName = $this->indexName;
        $currentSettings = $this->algoliaConnector->getSettings(
            $this->getIndexOptions($this->indexSuffix)
        );
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

        $this->assertConfigInDb(InstantSearchHelper::SORTING_INDICES, json_encode($sorting));

        $this->refreshConfigFromDb();

        $this->assertSortingAttribute($sortAttr, $sortDir);

        // Cannot use config fixture because we have disabled db isolation
        $this->setConfig(InstantSearchHelper::IS_ENABLED, 1);

        $this->indicesConfigurator->saveConfigurationToAlgolia(1);
        $this->algoliaConnector->waitLastTask();

        // Assert replica config created
        $currentSettings = $this->algoliaConnector->getSettings(
            $this->getIndexOptions($this->indexSuffix)
        );

        $this->assertArrayHasKey('replicas', $currentSettings);

        $replicaIndexName = $primaryIndexName . '_' . $sortAttr . '_' . $sortDir;

        $this->assertTrue($this->isVirtualReplica($currentSettings['replicas'], $replicaIndexName));
        $this->assertFalse($this->isStandardReplica($currentSettings['replicas'], $replicaIndexName));

        // Assert replica index created
        $replicaSettings = $this->assertReplicaIndexExists($primaryIndexName, $replicaIndexName);
        $this->assertVirtualReplicaRanking($replicaSettings, "$sortDir($sortAttr)");

        // Restore prior state (for this test only)
        $this->configHelper->setSorting($ogSortingState);
        $this->setConfig(InstantSearchHelper::IS_ENABLED, 0);
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
        // Make one replica virtual
        $this->mockSortUpdate('price', 'desc', ['virtualReplica' => 1]);

        $sorting = $this->populateReplicas(1);

        $rebuildCmd = $this->objectManager->get(\Algolia\AlgoliaSearch\Console\Command\Replica\ReplicaRebuildCommand::class);
        $this->invokeMethod(
            $rebuildCmd,
            'execute',
            [
                $this->createMock(\Symfony\Component\Console\Input\InputInterface::class),
                $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class)
            ]
        );
        $this->algoliaConnector->waitLastTask();

        $replicas = $this->assertReplicasCreated($sorting);

        $this->assertSortToReplicaConfigParity($this->indexName, $sorting, $replicas);
    }

    /**
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws \ReflectionException
     */
    public function testReplicaSync(): void
    {
        // Make one replica virtual
        $this->mockSortUpdate('created_at', 'desc', ['virtualReplica' => 1]);

        $sorting = $this->populateReplicas(1);

        $replicas = $this->assertReplicasCreated($sorting);

        $this->assertSortToReplicaConfigParity($this->indexName, $sorting, $replicas);
    }

    /**
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 0
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws \ReflectionException
     */
    public function testReplicaSyncDisabled(): void
    {
        $indexOptions = $this->getIndexOptions($this->indexSuffix);
        $settings = $this->algoliaConnector->getSettings($indexOptions);

        $this->assertArrayNotHasKey(ReplicaManager::ALGOLIA_SETTINGS_KEY_REPLICAS, $settings);

        $this->populateReplicas(1);

        $newSettings = $this->algoliaConnector->getSettings($indexOptions);
        $this->assertArrayNotHasKey(ReplicaManager::ALGOLIA_SETTINGS_KEY_REPLICAS, $newSettings);
    }

    /**
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     */
    public function testReplicaDelete(): void
    {
        // Make one replica virtual
        $this->mockSortUpdate('price', 'asc', ['virtualReplica' => 1]);

        $replicas = $this->assertReplicasCreated($this->populateReplicas(1));

        $this->replicaManager->deleteReplicasFromAlgolia(1);

        $this->assertReplicasDeleted($replicas);
    }

    /**
     * Test failure to clear index replica setting
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     */
    public function testReplicaDeleteUnreliable(): void
    {
        $replicas = $this->assertReplicasCreated($this->populateReplicas(1));

        $this->getMustPrevalidateMockReplicaManager()->deleteReplicasFromAlgolia(1);

        $this->assertReplicasDeleted($replicas);
    }

    /**
     * Test the RebuildReplicasPatch with API failures
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 1
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     */
    public function testReplicaRebuildPatch(): void
    {
        $currentStoreId = 1;

        $credentialsManager = $this->objectManager->get(AlgoliaCredentialsManager::class);
        $this->assertTrue($credentialsManager->checkCredentials(), "Credentials not available to apply patch.");
        $this->assertTrue($this->replicaManager->isReplicaSyncEnabled($currentStoreId), "Replica sync is not enabled for test store $currentStoreId.");

        $sorting = $this->populateReplicas($currentStoreId);
        $replicas = $this->assertReplicasCreated($sorting);

        $patch = new \Algolia\AlgoliaSearch\Setup\Patch\Data\RebuildReplicasPatch(
            $this->objectManager->get(ModuleDataSetupInterface::class),
            $this->objectManager->get(StoreManagerInterface::class),
            $this->getTroublesomePatchReplicaManager($replicas),
            $this->objectManager->get(ProductHelper::class),
            $this->objectManager->get(AppState::class),
            $this->objectManager->get(ReplicaState::class),
            $credentialsManager,
            $this->objectManager->get(LoggerInterface::class)
        );

        $patch->apply();

        $this->algoliaConnector->waitLastTask();

        $replicas = $this->assertReplicasCreated($sorting);

        $this->assertSortToReplicaConfigParity($this->indexName, $sorting, $replicas);
    }

    protected function extractIndexFromReplicaSetting(string $setting): string {
        return preg_replace('/^virtual\((.*)\)$/', '$1', $setting);
    }

    /**
     * If a replica fails to detach from the primary it can create deletion errors
     * Typically this is the result of latency even if task reports as completed from the API (hypothesis)
     * This aims to reproduce this potential scenario by not disassociating the replica
     *
     */
    protected function getMustPrevalidateMockReplicaManager(): ReplicaManagerInterface
    {
        $mockedMethod = 'clearReplicasSettingInAlgolia';

        $mock = $this->getMockReplicaManager([
            $mockedMethod => function(...$params) {
                //DO NOTHING
                return;
            }
        ]);
        $mock->expects($this->once())->method($mockedMethod);
        return $mock;
    }

    /**
     * This mock is to recreate the scenario where a patch tries to apply up to 3 times but the replicas
     * are never detached which throws a replica delete error until the last attempt which should succeed
     *
     * @param array $replicas - replicas that are to be deleted
     */
    protected function getTroublesomePatchReplicaManager(array $replicas): ReplicaManager
    {
        $mock = $this->getMockReplicaManager([
            'clearReplicasSettingInAlgolia' => null,
            'deleteReplicas' => null
        ]);
        $mock
            ->expects($this->exactly($this->patchRetries))
            ->method('clearReplicasSettingInAlgolia')
            ->willReturnCallback(function(...$params) use ($mock) {
                if (--$this->patchRetries) return;
                $originalMethod = new \ReflectionMethod(ReplicaManager::class, 'clearReplicasSettingInAlgolia');
                $originalMethod->invoke($mock, ...$params);
            });
        $mock
            ->expects($this->any())
            ->method('deleteReplicas')
            ->willReturnCallback(function(int $storeId, array $replicasToDelete, ...$params) use ($mock, $replicas) {
                $originalMethod = new \ReflectionMethod(ReplicaManager::class, 'deleteReplicas');
                $originalMethod->invoke($mock, $storeId, $replicasToDelete, false, false);
                if ($this->patchRetries) return;
                $this->runOnce(
                    function() use ($replicas) {
                        $this->algoliaConnector->waitLastTask();
                        $this->assertReplicasDeleted($replicas);
                    },
                    'patchDeleteTest'
                );
            });

        return $mock;
    }

    protected function getMockReplicaManager($mockedMethods = []): MockObject & ReplicaManager
    {
        $mockedClass = ReplicaManager::class;
        $mockedReplicaManager = $this->getMockBuilder($mockedClass)
            ->setConstructorArgs([
                $this->configHelper,
                $this->algoliaConnector,
                $this->objectManager->get(IndexOptionsBuilder::class),
                $this->objectManager->get(ReplicaState::class),
                $this->objectManager->get(VirtualReplicaValidatorFactory::class),
                $this->objectManager->get(IndexNameFetcher::class),
                $this->objectManager->get(StoreNameFetcher::class),
                $this->objectManager->get(SortingTransformer::class),
                $this->objectManager->get(StoreManagerInterface::class),
                $this->objectManager->get(DiagnosticsLogger::class)
            ])
            ->onlyMethods(array_keys($mockedMethods))
            ->getMock();

        foreach ($mockedMethods as $method => $callback) {
            if (!$callback) continue;
            $mockedReplicaManager
                ->method($method)
                ->willReturnCallback($callback);
        }

        return $mockedReplicaManager;
    }

    /**
     * Setup replicas for testing and assert that they have been synced to Algolia
     * @param array $sorting - the array of sorts from Magento
     * @return array - The replica setting from Algolia
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws \ReflectionException
     */
    protected function assertReplicasCreated(array $sorting): array
    {
        $currentSettings = $this->algoliaConnector->getSettings(
            $this->getIndexOptions($this->indexSuffix)
        );
        $this->assertArrayHasKey(ReplicaManager::ALGOLIA_SETTINGS_KEY_REPLICAS, $currentSettings);
        $replicas = $currentSettings[ReplicaManager::ALGOLIA_SETTINGS_KEY_REPLICAS];

        $this->assertEquals(count($sorting), count($replicas));

        return $replicas;
    }

    protected function assertReplicasDeleted($originalReplicas): void
    {
        $newSettings = $this->algoliaConnector->getSettings(
            $this->getIndexOptions($this->indexSuffix)
        );
        $this->assertArrayNotHasKey(ReplicaManager::ALGOLIA_SETTINGS_KEY_REPLICAS, $newSettings);
        foreach ($originalReplicas as $replica) {
            $this->assertIndexNotExists($this->extractIndexFromReplicaSetting($replica));
        }
    }

    /**
     * Populate replica indices for test based on store id and return sorting configuration used
     *
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws \ReflectionException
     */
    protected function populateReplicas(int $storeId): array
    {
        $sorting = $this->objectManager->get(\Algolia\AlgoliaSearch\Service\Product\SortingTransformer::class)->getSortingIndices($storeId, null, null, true);

        $cmd = $this->objectManager->get(\Algolia\AlgoliaSearch\Console\Command\Replica\ReplicaSyncCommand::class);

        $this->mockProperty($cmd, 'output', \Symfony\Component\Console\Output\OutputInterface::class);

        $cmd->syncReplicas();
        $this->algoliaConnector->waitLastTask();

        return $sorting;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Makes sure that the original values are restored in case a test is failing and doesn't finish
        $this->configHelper->setSorting(
            [
                [
                    'attribute' => 'price',
                    'sort' => 'asc',
                    'sortLabel' => 'Lowest Price'
                ],
                [
                    'attribute' => 'price',
                    'sort' => 'desc',
                    'sortLabel' => 'Highest Price'
                ],
                [
                    'attribute' => 'created_at',
                    'sort' => 'desc',
                    'sortLabel' => 'Newest first'
                ]
            ]
        );
    }

}
