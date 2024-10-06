<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Model\Indexer\Product as ProductIndexer;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Test\Integration\IndexingTestCase;

class ReplicaIndexingTest extends IndexingTestCase
{
    protected ?ReplicaManagerInterface $replicaManager;
    protected ?ProductIndexer $productIndexer;

    protected ?IndicesConfigurator $indicesConfigurator;

    protected ?string $indexSuffix;

    protected ?array $ogSortingState;

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
        $this->assertTrue(!$this->hasSortingAttribute($sortAttr, $sortDir));
    }

    public function testReplicaLimits() {
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

    /**
     * @magentoDbIsolation disabled
     */
    public function testVirtualReplicaConfig(): void
    {
        $productHelper = $this->objectManager->get(ProductHelper::class);
        $sortAttr = 'color';
        $sortDir = 'asc';
        $attributes = $productHelper->getAllAttributes();
        $this->assertArrayHasKey($sortAttr, $attributes);

        $this->assertNoSortingAttribute($sortAttr, $sortDir);

        $sorting = $this->configHelper->getSorting();
        $sorting[] = [
            'attribute' => $sortAttr,
            'sort' => $sortDir,
            'sortLabel' => $sortAttr
        ];
        $encoded = json_encode($sorting);
//        $this->setConfig('algoliasearch_instant/instant_sorts/sorts', $encoded);
        $this->configHelper->setSorting($sorting);

        $connection = $this->objectManager->create(\Magento\Framework\App\ResourceConnection::class)
            ->getConnection();
//        $connection->beginTransaction();
//        $this->objectManager->get(\Magento\Framework\App\Config\Storage\WriterInterface::class)->save(
//            \Algolia\AlgoliaSearch\Helper\ConfigHelper::SORTING_INDICES,
//            $encoded,
//            'default'
//        );
//        $connection->commit();


        $select = $connection->select()
            ->from('core_config_data', 'value')
            ->where('path = ?', 'algoliasearch_instant/instant_sorts/sorts')
            ->where('scope = ?', 'default')
            ->where('scope_id = ?', 0);

        $configValue = $connection->fetchOne($select);

        // Assert that the correct value was written to the database
        $this->assertEquals($encoded, $configValue);

//        $this->assertSortingAttribute($sortAttr, $sortDir);

    }

    public function tearDown(): void
    {
        $this->configHelper->setSorting($this->ogSortingState);
    }
}
