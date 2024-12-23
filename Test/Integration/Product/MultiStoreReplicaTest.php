<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Console\Command\ReplicaRebuildCommand;
use Algolia\AlgoliaSearch\Console\Command\ReplicaSyncCommand;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Product\SortingTransformer;
use Algolia\AlgoliaSearch\Test\Integration\Config\Traits\ConfigAssertionsTrait;
use Algolia\AlgoliaSearch\Test\Integration\MultiStoreTestCase;
use Algolia\AlgoliaSearch\Test\Integration\Product\Traits\ReplicaAssertionsTrait;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
 */
class MultiStoreReplicaTest extends MultiStoreTestCase
{
    use ReplicaAssertionsTrait;
    use ConfigAssertionsTrait;

    const COLOR_ATTR = 'color';
    const CREATED_AT_ATTR = 'created_at';
    const ASC_DIR = 'asc';
    const DESC_DIR = 'desc';

    protected ?ReplicaManagerInterface $replicaManager = null;

    protected ?SerializerInterface $serializer = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->replicaManager = $this->objectManager->get(ReplicaManagerInterface::class);
        $this->serializer = $this->objectManager->get(SerializerInterface::class);

        $stores = $this->storeManager->getStores();

        foreach ($stores as $store) {
            $this->setupStore($store, true);
        }
    }

    public function testMultiStoreReplicaConfig()
    {
        $defaultStore = $this->storeRepository->get('default');
        $fixtureSecondStore = $this->storeRepository->get('fixture_second_store');

        // Check replica config for created_at desc
        $this->checkReplicaIsStandard($defaultStore, self::CREATED_AT_ATTR, self::DESC_DIR);
        $this->checkReplicaIsStandard($fixtureSecondStore, self::CREATED_AT_ATTR, self::DESC_DIR);

        // add color asc sorting (virtual on a single store)
        $this->addSortingByStore($defaultStore, self::COLOR_ATTR, self::ASC_DIR);
        $this->addSortingByStore($fixtureSecondStore, self::COLOR_ATTR, self::ASC_DIR,true);

        // Check replica config for color asc
        $this->checkReplicaIsStandard($defaultStore, self::COLOR_ATTR, self::ASC_DIR);
        $this->checkReplicaIsVirtual($fixtureSecondStore, self::COLOR_ATTR, self::ASC_DIR);

        $this->resetAllSortings();
    }

    public function testCustomerGroupsConfig()
    {
        $defaultStore = $this->storeRepository->get('default');
        $fixtureSecondStore = $this->storeRepository->get('fixture_second_store');
        $fixtureThirdStore = $this->storeRepository->get('fixture_third_store');

        // Enable customer groups for second fixture store and save configuration
        $this->setConfig( ConfigHelper::CUSTOMER_GROUPS_ENABLE, 1, $fixtureSecondStore->getCode());
        $this->indicesConfigurator->saveConfigurationToAlgolia($fixtureSecondStore->getId());
        $this->algoliaHelper->waitLastTask($fixtureSecondStore->getId());

        // 7 indices for default store and third store:
        // - 1 for categories
        // - 1 for pages
        // - 1 for suggestions
        // - 4 for products (1 main and 3 for sorting replicas)
        $this->assertEquals(7, $this->countStoreIndices($defaultStore));
        $this->assertEquals(7, $this->countStoreIndices($fixtureThirdStore));

        // 13 indices for second store:
        // - 1 for categories
        // - 1 for pages
        // - 1 for suggestions
        // - 10 for products (1 main and 9 replicas (2 prices sortings * 4 customer groups + 1 other sorting (created_at))
        $this->assertEquals(13, $this->countStoreIndices($fixtureSecondStore));

        $this->resetAllSortings();
    }

    public function testReplicaCommands()
    {
        $defaultStore = $this->storeRepository->get('default');
        $fixtureSecondStore = $this->storeRepository->get('fixture_second_store');

        $defaultIndexName = $this->indexPrefix . $defaultStore->getCode() . '_products';
        $fixtureIndexName = $this->indexPrefix . $fixtureSecondStore->getCode() . '_products';

        // Update store config for fixture only
        $this->mockSortUpdate('price', 'desc', ['virtualReplica' => 1], $fixtureSecondStore);

        $defaultSortings = $this->objectManager->get(SortingTransformer::class)->getSortingIndices(
            $defaultStore->getId(),
            null,
            null,
            true
        );

        $fixtureSortings = $this->objectManager->get(SortingTransformer::class)->getSortingIndices(
            $fixtureSecondStore->getId(),
            null,
            null,
            true
        );

        // Executing commands - Start
        $syncCmd = $this->objectManager->get(ReplicaSyncCommand::class);
        $this->mockProperty($syncCmd, 'output', OutputInterface::class);
        $syncCmd->syncReplicas();
        $this->algoliaHelper->waitLastTask($defaultStore->getId());
        $this->algoliaHelper->waitLastTask($fixtureSecondStore->getId());

        $rebuildCmd = $this->objectManager->get(ReplicaRebuildCommand::class);
        $this->invokeMethod(
            $rebuildCmd,
            'execute',
            [
                $this->createMock(InputInterface::class),
                $this->createMock(OutputInterface::class)
            ]
        );
        $this->algoliaHelper->waitLastTask($defaultStore->getId());
        $this->algoliaHelper->waitLastTask($fixtureSecondStore->getId());
        // Executing commands - End

        $currentDefaultSettings = $this->algoliaHelper->getSettings($defaultIndexName, $defaultStore->getId());
        $currentFixtureSettings = $this->algoliaHelper->getSettings($fixtureIndexName, $fixtureSecondStore->getId());

        $this->assertArrayHasKey('replicas', $currentDefaultSettings);
        $this->assertArrayHasKey('replicas', $currentFixtureSettings);

        $defaultReplicas = $currentDefaultSettings['replicas'];
        $fixtureReplicas = $currentFixtureSettings['replicas'];

        $this->assertEquals(count($defaultSortings), count($defaultReplicas));
        $this->assertEquals(count($fixtureSortings), count($fixtureReplicas));

        $this->assertSortToReplicaConfigParity(
            $defaultIndexName,
            $defaultSortings,
            $defaultReplicas,
            $defaultStore->getId()
        );

        $this->assertSortToReplicaConfigParity(
            $fixtureIndexName,
            $fixtureSortings,
            $fixtureReplicas,
            $fixtureSecondStore->getId()
        );
    }

    protected function checkReplicaIsStandard(StoreInterface $store, $sortAttr, $sortDir)
    {
        $this->checkReplicaConfigByStore($store, $sortAttr, $sortDir, 'standard');
    }

    protected function checkReplicaIsVirtual(StoreInterface $store, $sortAttr, $sortDir)
    {
        $this->checkReplicaConfigByStore($store, $sortAttr, $sortDir, 'virtual');
    }

    protected function checkReplicaConfigByStore(StoreInterface $store, $sortAttr, $sortDir, $type)
    {
        $indexName = $this->indexPrefix . $store->getCode() . '_products';

        $settings = $this->algoliaHelper->getSettings($indexName, $store->getId());

        $this->assertArrayHasKey('replicas', $settings);

        $replicaIndexName = $indexName . '_' . $sortAttr . '_' . $sortDir;

        $type === 'virtual' ?
            $this->assertTrue($this->isVirtualReplica($settings['replicas'], $replicaIndexName)) :
            $this->assertTrue($this->isStandardReplica($settings['replicas'], $replicaIndexName));

        $replicaSettings = $this->assertReplicaIndexExists($indexName, $replicaIndexName, $store->getId());

        $type === 'virtual' ?
            $this->assertVirtualReplicaRanking($replicaSettings, "$sortDir($sortAttr)"):
            $this->assertStandardReplicaRanking($replicaSettings, "$sortDir($sortAttr)");
    }

    protected function addSortingByStore(StoreInterface $store, $attr, $dir,  $isVirtual = false)
    {
        $sorting = $this->configHelper->getSorting($store->getId());
        $newSorting = [
            'attribute'      => $attr,
            'sort'           => $dir,
            'sortLabel'      => $attr,
        ];

        if ($isVirtual) {
            $newSorting['virtualReplica'] = 1;
        }

        $sorting[] = $newSorting;

        $this->setConfig(
            ConfigHelper::SORTING_INDICES,
            $this->serializer->serialize($sorting),
            $store->getCode()
        );

        $this->assertSortingAttribute($attr, $dir);
        $this->indicesConfigurator->saveConfigurationToAlgolia($store->getId());
        $this->algoliaHelper->waitLastTask($store->getId());
    }

    protected function resetAllSortings()
    {
        $stores = $this->storeManager->getStores();

        foreach ($stores as $store) {
            $this->setConfig(
                ConfigHelper::SORTING_INDICES,
                $this->serializer->serialize([
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
                ]),
                $store->getCode()
            );

            $this->setConfig( ConfigHelper::CUSTOMER_GROUPS_ENABLE, 0, $store->getCode());
        }
    }

    protected function tearDown(): void
    {
        $this->resetAllSortings();

        parent::tearDown();
    }
}
