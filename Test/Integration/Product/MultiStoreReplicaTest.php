<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Test\Integration\MultiStoreTestCase;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Api\Data\StoreInterface;

/**
 * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
 */
class MultiStoreReplicaTest extends MultiStoreTestCase
{
    use ReplicaAssertionsTrait;

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

    protected function tearDown(): void
    {
        $stores = $this->storeManager->getStores();

        foreach ($stores as $store) {
            $this->setConfig(
                ConfigHelper::SORTING_INDICES,
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
                ],
                $store->getCode()
            );
        }

        parent::tearDown();
    }
}
