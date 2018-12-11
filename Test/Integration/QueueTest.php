<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Model\Indexer\Product;
use Algolia\AlgoliaSearch\Model\Indexer\QueueRunner;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class QueueTest extends TestCase
{
    /** @var AdapterInterface */
    private $connection;

    public function setUp()
    {
        parent::setUp();

        /** @var ResourceConnection $resouce */
        $resouce = $this->getObjectManager()->create('\Magento\Framework\App\ResourceConnection');
        $this->connection = $resouce->getConnection();
    }

    public function testFill()
    {
        $this->setConfig('algoliasearch_queue/queue/active', '1');
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        /** @var Product $indexer */
        $indexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Product');
        $indexer->executeFull();

        $rows = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertEquals(4, count($rows));

        $i = 0;
        foreach ($rows as $row) {
            $i++;

            $this->assertEquals('Algolia\AlgoliaSearch\Helper\Data', $row['class']);

            if ($i === 1) {
                $this->assertEquals('saveConfigurationToAlgolia', $row['method']);
                $this->assertEquals(1, $row['data_size']);

                continue;
            }

            if ($i < 4) {
                $this->assertEquals('rebuildProductIndex', $row['method']);
                $this->assertEquals(100, $row['data_size']);

                continue;
            }

            $this->assertEquals('moveIndex', $row['method']);
            $this->assertEquals(1, $row['data_size']);
        }
    }

    /** @depends testFill */
    public function testExecute()
    {
        $this->setConfig('algoliasearch_queue/queue/active', '1');

        /** @var Queue $queue */
        $queue = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Model\Queue');

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

        // Run the second two jobs - batch, move
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

        $rows = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertEquals(0, count($rows));
    }

    public function testSettings()
    {
        $this->resetConfigs([
            'algoliasearch_instant/instant/facets',
            'algoliasearch_products/products/product_additional_attributes',
        ]);

        $this->setConfig('algoliasearch_queue/queue/active', '1');

        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        // Reindex products multiple times
        /** @var Product $indexer */
        $indexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Product');
        $indexer->executeFull();
        $indexer->executeFull();
        $indexer->executeFull();

        $rows = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertEquals(12, count($rows));

        // Process the whole queue
        /** @var QueueRunner $queueRunner */
        $queueRunner = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\QueueRunner');
        $queueRunner->executeFull();
        $queueRunner->executeFull();
        $queueRunner->executeFull();

        $rows = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertEquals(0, count($rows));

        $this->algoliaHelper->waitLastTask();

        $settings = $this->algoliaHelper->getIndex($this->indexPrefix . 'default_products')->getSettings();
        $this->assertFalse(empty($settings['attributesForFaceting']), 'AttributesForFacetting should be set, but they are not.');
        $this->assertFalse(empty($settings['searchableAttributes']), 'SearchableAttributes should be set, but they are not.');
    }

    public function testMerging()
    {
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        $data = [
            [
                'job_id' => 1,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'rebuildStoreProductIndex',
                'data' => '{"store_id":"3","product_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ],
        ];

        $this->connection->insertMultiple('algoliasearch_queue', $data);

        /** @var Queue $queue */
        $queue = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Model\Queue');

        $jobs = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();

        $jobs = $this->invokeMethod($queue, 'prepareJobs', ['jobs' => $jobs]);
        $mergedJobs = $this->invokeMethod($queue, 'mergeJobs', ['jobs' => $jobs]);
        $this->assertEquals(6, count($mergedJobs));

        $expectedCategoryJob = [
            'job_id' => 7,
            'created' => '2017-09-01 12:00:00',
            'pid' => null,
            'class' => 'Algolia\AlgoliaSearch\Helper\Data',
            'method' => 'rebuildStoreCategoryIndex',
            'data' => [
                'store_id' => '1',
                'category_ids' => [
                    0 => '9',
                    1 => '22',
                    2 => '40',
                ],
            ],
            'max_retries' => '3',
            'retries' => '0',
            'error_log' => '',
            'data_size' => 3,
            'merged_ids' => ['1', '7'],
            'store_id' => '1',
        ];

        $this->assertEquals($expectedCategoryJob, $mergedJobs[0]);

        $expectedProductJob = [
            'job_id' => 10,
            'created' => '2017-09-01 12:00:00',
            'pid' => null,
            'class' => 'Algolia\AlgoliaSearch\Helper\Data',
            'method' => 'rebuildStoreProductIndex',
            'data' => [
                'store_id' => '1',
                'product_ids' => [
                    0 => '448',
                    1 => '405',
                ],
            ],
            'max_retries' => '3',
            'retries' => '0',
            'error_log' => '',
            'data_size' => 2,
            'merged_ids' => ['4', '10'],
            'store_id' => '1',
        ];

        $this->assertEquals($expectedProductJob, $mergedJobs[3]);
    }

    public function testMergingWithStaticMethods()
    {
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        $data = [
            [
                'job_id' => 1,
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'rebuildStoreCategoryIndex',
                'data' => '{"store_id":"1","category_ids":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 2,
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'rebuildStoreCategoryIndex',
                'data' => '{"store_id":"2","category_ids":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 3,
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'rebuildStoreCategoryIndex',
                'data' => '{"store_id":"3","category_ids":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 4,
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'deleteObjects',
                'data' => '{"store_id":"1","product_ids":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 5,
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'rebuildStoreProductIndex',
                'data' => '{"store_id":"2","product_ids":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 6,
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'rebuildStoreProductIndex',
                'data' => '{"store_id":"3","product_ids":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 7,
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'saveConfigurationToAlgolia',
                'data' => '{"store_id":"1","category_ids":["40"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 8,
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'rebuildStoreCategoryIndex',
                'data' => '{"store_id":"2","category_ids":["40"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 9,
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'moveIndex',
                'data' => '{"store_id":"3","category_ids":["40"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 10,
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'rebuildStoreProductIndex',
                'data' => '{"store_id":"1","product_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 11,
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'moveIndex',
                'data' => '{"store_id":"2","product_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 12,
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'rebuildStoreProductIndex',
                'data' => '{"store_id":"3","product_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ],
        ];

        /** @var Queue $queue */
        $queue = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Model\Queue');

        $this->connection->insertMultiple('algoliasearch_queue', $data);

        $jobs = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();

        $jobs = $this->invokeMethod($queue, 'prepareJobs', ['jobs' => $jobs]);
        $mergedJobs = $this->invokeMethod($queue, 'mergeJobs', ['jobs' => $jobs]);
        $this->assertEquals(12, count($mergedJobs));

        $this->assertEquals('rebuildStoreCategoryIndex', $jobs[0]['method']);
        $this->assertEquals('rebuildStoreCategoryIndex', $jobs[1]['method']);
        $this->assertEquals('rebuildStoreCategoryIndex', $jobs[2]['method']);
        $this->assertEquals('deleteObjects', $jobs[3]['method']);
        $this->assertEquals('rebuildStoreProductIndex', $jobs[4]['method']);
        $this->assertEquals('rebuildStoreProductIndex', $jobs[5]['method']);
        $this->assertEquals('saveConfigurationToAlgolia', $jobs[6]['method']);
        $this->assertEquals('rebuildStoreCategoryIndex', $jobs[7]['method']);
        $this->assertEquals('moveIndex', $jobs[8]['method']);
        $this->assertEquals('rebuildStoreProductIndex', $jobs[9]['method']);
        $this->assertEquals('moveIndex', $jobs[10]['method']);
        $this->assertEquals('rebuildStoreProductIndex', $jobs[11]['method']);
    }

    public function testGetJobs()
    {
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        $data = [
            [
                'job_id' => 1,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
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
                'class' => 'Algolia\AlgoliaSearch\Helper\Data',
                'method' => 'rebuildStoreProductIndex',
                'data' => '{"store_id":"3","product_ids":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ],
        ];

        $this->connection->insertMultiple('algoliasearch_queue', $data);

        /** @var Queue $queue */
        $queue = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Model\Queue');

        $pid = getmypid();
        $jobs = $this->invokeMethod($queue, 'getJobs', ['maxJobs' => 10, 'pid' => $pid]);
        $this->assertEquals(6, count($jobs));

        $expectedFirstJob = [
            'job_id' => 7,
            'created' => '2017-09-01 12:00:00',
            'pid' => null,
            'class' => 'Algolia\AlgoliaSearch\Helper\Data',
            'method' => 'rebuildStoreCategoryIndex',
            'data' => [
                'store_id' => '1',
                'category_ids' => [
                    0 => '9',
                    1 => '22',
                    2 => '40',
                ],
            ],
            'max_retries' => '3',
            'retries' => '0',
            'error_log' => '',
            'data_size' => 3,
            'merged_ids' => ['1', '7'],
            'store_id' => '1',
        ];

        $expectedLastJob = [
            'job_id' => 12,
            'created' => '2017-09-01 12:00:00',
            'pid' => null,
            'class' => 'Algolia\AlgoliaSearch\Helper\Data',
            'method' => 'rebuildStoreProductIndex',
            'data' => [
                'store_id' => '3',
                'product_ids' => [
                    0 => '448',
                    1 => '405',
                ],
            ],
            'max_retries' => '3',
            'retries' => '0',
            'error_log' => '',
            'data_size' => 2,
            'merged_ids' => ['6', '12'],
            'store_id' => '3',
        ];

        $this->assertEquals($expectedFirstJob, reset($jobs));
        $this->assertEquals($expectedLastJob, end($jobs));

        $dbJobs = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();

        $this->assertEquals(12, count($dbJobs));

        foreach ($dbJobs as $dbJob) {
            $this->assertEquals($pid, $dbJob['pid']);
        }
    }

    public function testHugeJob()
    {
        // Default value - maxBatchSize = 1000
        $this->setConfig('algoliasearch_queue/queue/number_of_job_to_run', 10);
        $this->setConfig('algoliasearch_advanced/advanced/number_of_element_by_page', 100);

        $productIds = range(1, 5000);
        $jsonProductIds = json_encode($productIds);

        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');
        $this->connection->query('INSERT INTO `algoliasearch_queue` (`job_id`, `pid`, `class`, `method`, `data`, `max_retries`, `retries`, `error_log`, `data_size`) VALUES
            (1, NULL, \'class\', \'rebuildStoreProductIndex\', \'{"store_id":"1","product_ids":' . $jsonProductIds . '}\', 3, 0, \'\', 5000),
            (2, NULL, \'class\', \'rebuildStoreProductIndex\', \'{"store_id":"2","product_ids":["9","22"]}\', 3, 0, \'\', 2);');

        /** @var Queue $queue */
        $queue = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Model\Queue');

        $pid = getmypid();
        $jobs = $this->invokeMethod($queue, 'getJobs', ['maxJobs' => 10, 'pid' => $pid]);

        $this->assertEquals(1, count($jobs));

        $job = reset($jobs);
        $this->assertEquals(5000, $job['data_size']);
        $this->assertEquals(5000, count($job['data']['product_ids']));

        $dbJobs = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();

        $this->assertEquals(2, count($dbJobs));

        $firstJob = reset($dbJobs);
        $lastJob = end($dbJobs);

        $this->assertEquals($pid, $firstJob['pid']);
        $this->assertNull($lastJob['pid']);
    }

    public function testMaxSingleJobSize()
    {
        // Default value - maxBatchSize = 1000
        $this->setConfig('algoliasearch_queue/queue/number_of_job_to_run', 10);
        $this->setConfig('algoliasearch_advanced/advanced/number_of_element_by_page', 100);

        $productIds = range(1, 99);
        $jsonProductIds = json_encode($productIds);

        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');
        $this->connection->query('INSERT INTO `algoliasearch_queue` (`job_id`, `pid`, `class`, `method`, `data`, `max_retries`, `retries`, `error_log`, `data_size`) VALUES
            (1, NULL, \'class\', \'rebuildStoreProductIndex\', \'{"store_id":"1","product_ids":' . $jsonProductIds . '}\', 3, 0, \'\', 99),
            (2, NULL, \'class\', \'rebuildStoreProductIndex\', \'{"store_id":"2","product_ids":["9","22"]}\', 3, 0, \'\', 2);');

        /** @var Queue $queue */
        $queue = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Model\Queue');

        $pid = getmypid();
        $jobs = $this->invokeMethod($queue, 'getJobs', ['maxJobs' => 10, 'pid' => $pid]);

        $this->assertEquals(2, count($jobs));

        $firstJob = reset($jobs);
        $lastJob = end($jobs);

        $this->assertEquals(99, $firstJob['data_size']);
        $this->assertEquals(99, count($firstJob['data']['product_ids']));

        $this->assertEquals(2, $lastJob['data_size']);
        $this->assertEquals(2, count($lastJob['data']['product_ids']));

        $dbJobs = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();

        $this->assertEquals(2, count($dbJobs));

        $firstJob = reset($dbJobs);
        $lastJob = end($dbJobs);

        $this->assertEquals($pid, $firstJob['pid']);
        $this->assertEquals($pid, $lastJob['pid']);
    }

    public function testMaxSingleJobsSizeOnProductReindex()
    {
        $this->setConfig('algoliasearch_queue/queue/active', '1');

        $this->setConfig('algoliasearch_queue/queue/number_of_job_to_run', 10);
        $this->setConfig('algoliasearch_advanced/advanced/number_of_element_by_page', 100);

        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        /** @var Product $indexer */
        $indexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Product');
        $indexer->execute(range(1, 512));

        $dbJobs = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertSame(10, count($dbJobs));

        $firstJob = reset($dbJobs);
        $lastJob = end($dbJobs);

        $this->assertEquals(100, (int) $firstJob['data_size']);
        $this->assertEquals(49, (int) $lastJob['data_size']);
    }
}
