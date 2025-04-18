<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Queue;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Indexer\Product;
use Algolia\AlgoliaSearch\Model\Indexer\QueueRunner;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Job;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job\CollectionFactory as JobsCollectionFactory;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class QueueTest extends TestCase
{
    private const INCOMPLETE_REASON = "Must revisit transaction handling across connections.";

    /** @var JobsCollectionFactory */
    private $jobsCollectionFactory;

    /** @var AdapterInterface */
    private $connection;

    /** @var Queue */
    private $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jobsCollectionFactory = $this->objectManager->create(JobsCollectionFactory::class);

        /** @var ResourceConnection $resource */
        $resource = $this->objectManager->get(ResourceConnection::class);
        $this->connection = $resource->getConnection();

        $this->queue = $this->objectManager->get(Queue::class);
    }

    public function testFill()
    {
        $this->resetConfigs([
            ConfigHelper::NUMBER_OF_JOB_TO_RUN,
            ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE,
        ]);

        $this->setConfig(ConfigHelper::IS_ACTIVE, '1');
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        /** @var Product $indexer */
        $indexer = $this->objectManager->get(Product::class);
        $indexer->executeFull();

        $rows = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertEquals(3, count($rows));

        $i = 0;
        foreach ($rows as $row) {
            $i++;

            if ($i === 1) {
                $this->assertEquals(IndicesConfigurator::class, $row['class']);
                $this->assertEquals('saveConfigurationToAlgolia', $row['method']);
                $this->assertEquals(1, $row['data_size']);

                continue;
            }

            if ($i < 3) {
                $this->assertEquals(\Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class, $row['class']);
                $this->assertEquals('buildIndexFull', $row['method']);
                $this->assertEquals(300, $row['data_size']);

                continue;
            }

            $this->assertEquals('moveIndexWithSetSettings', $row['method']);
            $this->assertEquals(1, $row['data_size']);
        }
    }

    public function testExecute()
    {
        $this->setConfig(ConfigHelper::IS_ACTIVE, '1');
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        /** @var Product $indexer */
        $indexer = $this->objectManager->get(Product::class);
        $indexer->executeFull();

        /** @var Queue $queue */
        $queue = $this->objectManager->get(Queue::class);

        // Run the first two jobs - saveSettings, batch
        $queue->runCron(2, true);

        $this->algoliaHelper->waitLastTask();

        $indices = $this->algoliaHelper->listIndexes();

        $existsDefaultTmpIndex = false;
        foreach ($indices['items'] as $index) {
            if ($index['name'] === $this->indexPrefix . 'default_products_tmp') {
                $existsDefaultTmpIndex = true;
            }
        }

        $this->assertTrue($existsDefaultTmpIndex, 'Default products production index does not exists and it should');

        // Run the last move - move
        $queue->runCron(2, true);

        $this->algoliaHelper->waitLastTask();

        $indices = $this->algoliaHelper->listIndexes();

        $existsDefaultProdIndex = false;
        $existsDefaultTmpIndex = false;
        foreach ($indices['items'] as $index) {
            if ($index['name'] === $this->indexPrefix . 'default_products') {
                $existsDefaultProdIndex = true;
            }

            if ($index['name'] === $this->indexPrefix . 'default_products_tmp') {
                $existsDefaultTmpIndex = true;
            }
        }

        $this->assertFalse($existsDefaultTmpIndex, 'Default product TMP index exists and it should not'); // Was already moved
        $this->assertTrue($existsDefaultProdIndex, 'Default product production index does not exists and it should');

        /** TODO: There are mystery items being added to queue from unknown save process on product_id=1 */
        $rows = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertEquals(0, count($rows));
    }

    public function testSettings()
    {
        $this->resetConfigs([
            ConfigHelper::NUMBER_OF_JOB_TO_RUN,
            ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE,
            ConfigHelper::FACETS,
            ConfigHelper::PRODUCT_ATTRIBUTES
        ]);

        $this->setConfig(ConfigHelper::IS_ACTIVE, '1');

        $this->connection->query('DELETE FROM algoliasearch_queue');

        // Reindex products multiple times
        /** @var Product $indexer */
        $indexer = $this->objectManager->get(Product::class);
        $indexer->executeFull();
        $indexer->executeFull();
        $indexer->executeFull();

        $rows = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertEquals(9, count($rows));

        // Process the whole queue
        /** @var QueueRunner $queueRunner */
        $queueRunner = $this->objectManager->get(QueueRunner::class);
        $queueRunner->executeFull();
        $queueRunner->executeFull();
        $queueRunner->executeFull();

        $rows = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertEquals(0, count($rows));

        $this->algoliaHelper->waitLastTask();

        $settings = $this->algoliaHelper->getSettings($this->indexPrefix . 'default_products');
        $this->assertFalse(empty($settings['attributesForFaceting']), 'AttributesForFacetting should be set, but they are not.');
        $this->assertFalse(empty($settings['searchableAttributes']), 'SearchableAttributes should be set, but they are not.');
    }

    public function testMergeSettings()
    {
        $this->setConfig(ConfigHelper::IS_ACTIVE, '1');
        $this->setConfig(ConfigHelper::NUMBER_OF_JOB_TO_RUN, 1);
        $this->setConfig(ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE, 300);

        $this->connection->query('DELETE FROM algoliasearch_queue');

        /** @var Product $productIndexer */
        $productIndexer = $this->objectManager->get(Product::class);
        $productIndexer->executeFull();

        $rows = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertCount(3, $rows);

        $productionIndexName = $this->indexPrefix . 'default_products';

        $res = $this->algoliaHelper->setSettings($productionIndexName, ['disableTypoToleranceOnAttributes' => ['sku']]);
        $this->algoliaHelper->waitLastTask();

        $settings = $this->algoliaHelper->getSettings($productionIndexName);
        $this->assertEquals(['sku'], $settings['disableTypoToleranceOnAttributes']);

        /** @var QueueRunner $queueRunner */
        $queueRunner = $this->objectManager->get(QueueRunner::class);
        $queueRunner->executeFull();

        $this->algoliaHelper->waitLastTask();

        $settings = $this->algoliaHelper->getSettings($this->indexPrefix . 'default_products_tmp');
        $this->assertEquals(['sku'], $settings['disableTypoToleranceOnAttributes']);

        $queueRunner->executeFull();
        $queueRunner->executeFull();

        $settings = $this->algoliaHelper->getSettings($productionIndexName);
        $this->assertEquals(['sku'], $settings['disableTypoToleranceOnAttributes']);
    }

    public function testMerging()
    {
        $this->connection->query('DELETE FROM algoliasearch_queue');

        $data = [
            [
                'job_id' => 1,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"1","entity_ids":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 2,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"2","entity_ids":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 3,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"3","entity_ids":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 4,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"1","entity_ids":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 5,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"2","entity_ids":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 6,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"3","entity_ids":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 7,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"1","entity_ids":["40"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 8,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"2","entity_ids":["40"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 9,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"3","entity_ids":["40"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 10,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"1","entity_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 11,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"2","entity_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 12,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"3","entity_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ],
        ];

        $this->connection->insertMultiple('algoliasearch_queue', $data);

        $jobs = $this->jobsCollectionFactory->create()->getItems();
        // $jobs = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();

        $mergedJobs = array_values($this->invokeMethod($this->queue, 'mergeJobs', [$jobs]));
        $this->assertEquals(6, count($mergedJobs));

        $expectedCategoryJob = [
            'job_id' => '1',
            'created' => '2017-09-01 12:00:00',
            'pid' => null,
            'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
            'method' => 'buildIndexList',
            'data' => '{"store_id":"1","entity_ids":["9","22"]}',
            'max_retries' => '3',
            'retries' => '0',
            'error_log' => '',
            'data_size' => 3,
            'merged_ids' => ['1', '7'],
            'store_id' => '1',
            'is_full_reindex' => '0',
            'decoded_data' => [
                'store_id' => '1',
                'entity_ids' => [
                    0 => '9',
                    1 => '22',
                    2 => '40',
                ],
            ],
            'locked_at' => null,
            'debug' => null,
        ];

        /** @var Job $categoryJob */
        $categoryJob = $mergedJobs[0];
        $this->assertEquals($expectedCategoryJob, $categoryJob->toArray());

        $expectedProductJob = [
            'job_id' => '4',
            'created' => '2017-09-01 12:00:00',
            'pid' => null,
            'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
            'method' => 'buildIndexList',
            'data' => '{"store_id":"1","entity_ids":["448"]}',
            'max_retries' => '3',
            'retries' => '0',
            'error_log' => '',
            'data_size' => 2,
            'merged_ids' => ['4', '10'],
            'store_id' => '1',
            'is_full_reindex' => '0',
            'decoded_data' => [
                'store_id' => '1',
                'entity_ids' => [
                    0 => '448',
                    1 => '405',
                ],
            ],
            'locked_at' => null,
            'debug' => null,
        ];

        /** @var Job $productJob */
        $productJob = $mergedJobs[3];
        $this->assertEquals($expectedProductJob, $productJob->toArray());
    }

    public function testMergingWithStaticMethods()
    {
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        $data = [
            [
                'job_id' => 1,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"1","entity_ids":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 2,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"2","entity_ids":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 3,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"3","entity_ids":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 4,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'deleteObjects',
                'data' => '{"store_id":"1","product_ids":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 5,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"2","entity_ids":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 6,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"3","entity_ids":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 7,
                'pid' => null,
                'class' => IndicesConfigurator::class,
                'method' => 'saveConfigurationToAlgolia',
                'data' => '{"store_id":"1","entity_ids":["40"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 8,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"2","entity_ids":["40"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 9,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Model\IndexMover::class,
                'method' => 'moveIndexWithSetSettings',
                'data' => '{"store_id":"3","entity_ids":["40"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 10,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"1","entity_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 11,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Model\IndexMover::class,
                'method' => 'moveIndexWithSetSettings',
                'data' => '{"store_id":"2","entity_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 12,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"store_id":"3","entity_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ],
        ];

        $this->connection->insertMultiple('algoliasearch_queue', $data);

        /** @var Job[] $jobs */
        $jobs = $this->jobsCollectionFactory->create()->getItems();

        $jobs = array_values($this->invokeMethod($this->queue, 'mergeJobs', [$jobs]));
        $this->assertEquals(12, count($jobs));

        $this->assertEquals('buildIndexList', $jobs[0]->getMethod());
        $this->assertEquals('buildIndexList', $jobs[1]->getMethod());
        $this->assertEquals('buildIndexList', $jobs[2]->getMethod());
        $this->assertEquals('deleteObjects', $jobs[3]->getMethod());
        $this->assertEquals('buildIndexList', $jobs[4]->getMethod());
        $this->assertEquals('buildIndexList', $jobs[5]->getMethod());
        $this->assertEquals('saveConfigurationToAlgolia', $jobs[6]->getMethod());
        $this->assertEquals('buildIndexList', $jobs[7]->getMethod());
        $this->assertEquals('moveIndexWithSetSettings', $jobs[8]->getMethod());
        $this->assertEquals('buildIndexList', $jobs[9]->getMethod());
        $this->assertEquals('moveIndexWithSetSettings', $jobs[10]->getMethod());
        $this->assertEquals('buildIndexList', $jobs[11]->getMethod());
    }

    public function testGetJobs()
    {
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        $data = [
            [
                'job_id' => 1,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'rebuildStoreCategoryIndex',
                'data' => '{"store_id":"1","category_ids":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 2,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'rebuildStoreCategoryIndex',
                'data' => '{"store_id":"2","category_ids":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 3,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'rebuildStoreCategoryIndex',
                'data' => '{"store_id":"3","category_ids":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 4,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'rebuildStoreProductIndex',
                'data' => '{"store_id":"1","product_ids":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 5,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'rebuildStoreProductIndex',
                'data' => '{"store_id":"2","product_ids":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 6,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'rebuildStoreProductIndex',
                'data' => '{"store_id":"3","product_ids":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 7,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'rebuildStoreCategoryIndex',
                'data' => '{"store_id":"1","category_ids":["40"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 8,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'rebuildStoreCategoryIndex',
                'data' => '{"store_id":"2","category_ids":["40"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 9,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'rebuildStoreCategoryIndex',
                'data' => '{"store_id":"3","category_ids":["40"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 10,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'rebuildStoreProductIndex',
                'data' => '{"store_id":"1","product_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 11,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'rebuildStoreProductIndex',
                'data' => '{"store_id":"2","product_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 12,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'rebuildStoreProductIndex',
                'data' => '{"store_id":"3","product_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ],
        ];

        $this->connection->insertMultiple('algoliasearch_queue', $data);

        $pid = getmypid();
        $jobs = $this->invokeMethod($this->queue, 'getJobs', ['maxJobs' => 10]);
        $this->assertEquals(6, count($jobs));

        $expectedFirstJob = [
            'job_id' => '1',
            'pid' => $pid,
            'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
            'method' => 'rebuildStoreCategoryIndex',
            'data' => '{"store_id":"1","category_ids":["9","22"]}',
            'merged_ids' => ['1', '7'],
            'store_id' => '1',
            'decoded_data' => [
                'store_id' => '1',
                'category_ids' => [
                    0 => '9',
                    1 => '22',
                    2 => '40',
                ],
            ],
        ];

        $expectedLastJob = [
            'job_id' => '6',
            'pid' => $pid,
            'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
            'method' => 'rebuildStoreProductIndex',
            'data' => '{"store_id":"3","product_ids":["448"]}',
            'merged_ids' => ['6', '12'],
            'store_id' => '3',
            'decoded_data' => [
                'store_id' => '3',
                'product_ids' => [
                    0 => '448',
                    1 => '405',
                ],
            ],
        ];

        /** @var Job $firstJob */
        $firstJob = reset($jobs);
        $firstJob = $firstJob->toArray();

        /** @var Job $lastJob */
        $lastJob = end($jobs);
        $lastJob = $lastJob->toArray();

        $valuesToCheck = [
            'job_id',
            'method',
            'class',
            'store_id',
            'pid',
            'data',
            'merged_ids',
            'decoded_data',
        ];

        foreach ($valuesToCheck as $valueToCheck) {
            $this->assertEquals($expectedFirstJob[$valueToCheck], $firstJob[$valueToCheck]);
            $this->assertEquals($expectedLastJob[$valueToCheck], $lastJob[$valueToCheck]);
        }

        $dbJobs = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();

        $this->assertEquals(12, count($dbJobs));

        foreach ($dbJobs as $dbJob) {
            $this->assertEquals($pid, $dbJob['pid']);
        }
    }

    public function testHugeJob()
    {
        // Default value - maxBatchSize = 1000
        $this->setConfig(ConfigHelper::NUMBER_OF_JOB_TO_RUN, 10);
        $this->setConfig(ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE, 100);

        $productIds = range(1, 5000);
        $jsonProductIds = json_encode($productIds);

        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');
        $this->connection->query('INSERT INTO `algoliasearch_queue` (`job_id`, `pid`, `class`, `method`, `data`, `max_retries`, `retries`, `error_log`, `data_size`) VALUES
            (1, NULL, \'class\', \'rebuildStoreProductIndex\', \'{"store_id":"1","product_ids":' . $jsonProductIds . '}\', 3, 0, \'\', 5000),
            (2, NULL, \'class\', \'rebuildStoreProductIndex\', \'{"store_id":"2","product_ids":["9","22"]}\', 3, 0, \'\', 2);');

        $pid = getmypid();
        /** @var Job[] $jobs */
        $jobs = $this->invokeMethod($this->queue, 'getJobs', ['maxJobs' => 10]);

        $this->assertEquals(1, count($jobs));

        $job = reset($jobs);
        $this->assertEquals(5000, $job->getDataSize());
        $this->assertEquals(5000, count($job->getDecodedData()['product_ids']));

        $dbJobs = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();

        $this->assertEquals(2, count($dbJobs));

        $firstJob = reset($dbJobs);
        $lastJob = end($dbJobs);

        $this->assertEquals($pid, $firstJob['pid']);
        $this->assertNull($lastJob['pid']);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testMaxSingleJobSize()
    {
        // Default value - maxBatchSize = 1000
        $this->setConfig(ConfigHelper::NUMBER_OF_JOB_TO_RUN, 10);
        $this->setConfig(ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE, 100);

        $productIds = range(1, 99);
        $jsonProductIds = json_encode($productIds);

        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');
        $this->connection->query('INSERT INTO `algoliasearch_queue` (`job_id`, `pid`, `class`, `method`, `data`, `max_retries`, `retries`, `error_log`, `data_size`) VALUES
            (1, NULL, \'class\', \'rebuildStoreProductIndex\', \'{"store_id":"1","product_ids":' . $jsonProductIds . '}\', 3, 0, \'\', 99),
            (2, NULL, \'class\', \'rebuildStoreProductIndex\', \'{"store_id":"2","product_ids":["9","22"]}\', 3, 0, \'\', 2);');

        $pid = getmypid();

        /** @var Job[] $jobs */
        $jobs = $this->invokeMethod($this->queue, 'getJobs', ['maxJobs' => 10]);

        $this->assertEquals(2, count($jobs));

        $firstJob = reset($jobs);
        $lastJob = end($jobs);

        $this->assertEquals(99, $firstJob->getDataSize());
        $this->assertEquals(99, count($firstJob->getDecodedData()['product_ids']));

        $this->assertEquals(2, $lastJob->getDataSize());
        $this->assertEquals(2, count($lastJob->getDecodedData()['product_ids']));

        $dbJobs = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();

        $this->assertEquals(2, count($dbJobs));

        $firstJob = reset($dbJobs);
        $lastJob = end($dbJobs);

        $this->assertEquals($pid, $firstJob['pid']);
        $this->assertEquals($pid, $lastJob['pid']);
    }

    public function testMaxSingleJobsSizeOnProductReindex()
    {
        $this->resetConfigs([
            ConfigHelper::NUMBER_OF_JOB_TO_RUN,
            ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE,
        ]);

        $this->setConfig(ConfigHelper::IS_ACTIVE, '1');

        $this->setConfig(ConfigHelper::NUMBER_OF_JOB_TO_RUN, 10);
        $this->setConfig(ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE, 100);

        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        /** @var Product $indexer */
        $indexer = $this->objectManager->get(Product::class);
        $indexer->execute(range(1, 512));

        $dbJobs = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertSame(6, count($dbJobs));

        $firstJob = reset($dbJobs);
        $lastJob = end($dbJobs);

        $this->assertEquals(100, (int) $firstJob['data_size']);

        $this->assertEquals($this->assertValues->lastJobDataSize, (int) $lastJob['data_size']);
    }
}
