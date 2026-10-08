<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Queue;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Model\Indexer\QueueRunner;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Job;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job\CollectionFactory as JobsCollectionFactory;
use Algolia\AlgoliaSearch\Service\Product\BatchQueueProcessor as ProductBatchQueueProcessor;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * @magentoDbIsolation disabled
 *
 * @magentoAppIsolation enabled
 */
class QueueTest extends TestCase
{
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

        $this->queue = $this->objectManager->create(Queue::class);
    }

    public function testFill()
    {
        $this->resetConfigs([
            QueueHelper::NUMBER_OF_JOB_TO_RUN,
            ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE,
        ]);

        $this->setConfig(QueueHelper::IS_ACTIVE, '1');
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        $storeId = 1;

        $productBatchQueueProcessor = $this->objectManager->get(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->processBatch($storeId);

        $rows = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertEquals(3, count($rows));

        $i = 0;
        foreach ($rows as $row) {
            $i++;

            $this->assertSame($storeId, (int) $row['store_id']);

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
        $this->setConfig(QueueHelper::IS_ACTIVE, '1');
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        $productBatchQueueProcessor = $this->objectManager->get(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->processBatch(1);

        /** @var Queue $queue */
        $queue = $this->objectManager->get(Queue::class);

        // Run the first two jobs - saveSettings, batch
        $queue->runCron(2, true);

        $this->algoliaConnector->waitLastTask();

        $indices = $this->algoliaConnector->listIndexes();

        $existsDefaultTmpIndex = false;
        foreach ($indices['items'] as $index) {
            if ($index['name'] === $this->indexPrefix . 'default_products_tmp') {
                $existsDefaultTmpIndex = true;
            }
        }

        $this->assertTrue($existsDefaultTmpIndex, 'Default products production index does not exists and it should');

        $indexOptions = $this->getIndexOptions('products', 1);
        $settings = $this->algoliaConnector->getSettings($indexOptions);

        $indexOptionsTmp = $this->getIndexOptions('products', 1, true);
        $settingsTmp = $this->algoliaConnector->getSettings($indexOptionsTmp);

        // Asserts that the prod settings have been copied successfully to tmp
        $this->assertEquals($settings, $settingsTmp);

        // Run the last move - move
        $queue->runCron(2, true);

        $this->algoliaConnector->waitLastTask();

        $indices = $this->algoliaConnector->listIndexes();

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

    public function testExecuteWithWrongJobClass()
    {
        $this->setConfig(QueueHelper::IS_ACTIVE, '1');
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        $data = [
            [
                'job_id' => 1,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => '\Algolia\AlgoliaSearch\Dummy\Class',
                'method' => 'buildIndexList',
                'data' => '{"storeId":"1","entityIds":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ],
        ];

        $this->connection->insertMultiple('algoliasearch_queue', $data);

        try {
            $this->queue->runCron();
        } catch (AlgoliaException $e) {
            $this->expectException(AlgoliaException::class);
            $this->expectExceptionMessage('Unauthorized job handler');
        }
    }

    public function testExecuteWithWrongJobMethod()
    {
        $this->setConfig(QueueHelper::IS_ACTIVE, '1');
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        $data = [
            [
                'job_id' => 1,
                'created' => '2017-09-01 12:00:00',
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'dummyMethod',
                'data' => '{"storeId":"1","entityIds":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ],
        ];

        $this->connection->insertMultiple('algoliasearch_queue', $data);

        try {
            $this->queue->runCron();
        } catch (AlgoliaException $e) {
            $this->expectException(AlgoliaException::class);
            $this->expectExceptionMessage('Unauthorized job handler');
        }
    }

    public function testTmpIndexConfig()
    {
        $this->setConfig(QueueHelper::IS_ACTIVE, '1');
        // Setting "Use a temporary index for full products reindex" configuration to "No"
        $this->setConfig(QueueHelper::USE_TMP_INDEX, '0');
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        $productBatchQueueProcessor = $this->objectManager->get(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->processBatch(1);

        $rows = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        // Without temporary index enabled, there are only 2 jobs (saveConfigurationToAlgolia and buildIndexFull)
        $this->assertEquals(2, count($rows));

        $indexingJob = $rows[1];
        $jobData = json_decode($indexingJob['data'], true);

        $this->assertEquals('buildIndexFull', $indexingJob['method']);
        $this->assertFalse($jobData['options']['useTmpIndex']);

        /** @var Queue $queue */
        $queue = $this->objectManager->get(Queue::class);

        // Run the first job (saveSettings)
        $queue->runCron(1, true);

        $this->algoliaConnector->waitLastTask();

        $indices = $this->algoliaConnector->listIndexes();

        $existsDefaultTmpIndex = false;
        foreach ($indices['items'] as $index) {
            if ($index['name'] === $this->indexPrefix . 'default_products_tmp') {
                $existsDefaultTmpIndex = true;
            }
        }
        // Checking if the temporary index hasn't been created
        $this->assertFalse($existsDefaultTmpIndex, 'Temporary index exists and it should not');
    }

    public function testSettings()
    {
        $this->resetConfigs([
            QueueHelper::NUMBER_OF_JOB_TO_RUN,
            ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE,
            ConfigHelper::FACETS,
            QueueHelper::USE_TMP_INDEX,
            ConfigHelper::PRODUCT_ATTRIBUTES,
        ]);

        $this->setConfig(QueueHelper::IS_ACTIVE, '1');
        $this->setConfig(ConfigHelper::ENABLE_INDEXER_QUEUE, '1');

        $this->connection->query('DELETE FROM algoliasearch_queue');

        // Reindex products multiple times
        $productBatchQueueProcessor = $this->objectManager->get(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->processBatch(1);
        $productBatchQueueProcessor->processBatch(1);
        $productBatchQueueProcessor->processBatch(1);

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

        $this->algoliaConnector->waitLastTask();

        $indexOptions = $this->getIndexOptions('products');
        $settings = $this->algoliaConnector->getSettings($indexOptions);
        $this->assertFalse(empty($settings['attributesForFaceting']), 'AttributesForFacetting should be set, but they are not.');
        $this->assertFalse(empty($settings['searchableAttributes']), 'SearchableAttributes should be set, but they are not.');
    }

    public function testMergeSettings()
    {
        $this->setConfig(QueueHelper::IS_ACTIVE, '1');
        $this->setConfig(ConfigHelper::ENABLE_INDEXER_QUEUE, '1');
        $this->setConfig(QueueHelper::NUMBER_OF_JOB_TO_RUN, 1);
        $this->setConfig(ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE, 300);

        $this->connection->query('DELETE FROM algoliasearch_queue');

        $productBatchQueueProcessor = $this->objectManager->get(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->processBatch(1);

        $rows = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertCount(3, $rows);

        $productionIndexOptions = $this->getIndexOptions('products');
        $this->algoliaConnector->setSettings($productionIndexOptions, ['disableTypoToleranceOnAttributes' => ['sku']]);
        $this->algoliaConnector->waitLastTask();

        $settings = $this->algoliaConnector->getSettings($productionIndexOptions);
        $this->assertEquals(['sku'], $settings['disableTypoToleranceOnAttributes']);

        /** @var QueueRunner $queueRunner */
        $queueRunner = $this->objectManager->get(QueueRunner::class);
        $queueRunner->executeFull();

        $this->algoliaConnector->waitLastTask();

        $tmpIndexOptions =  $this->getIndexOptions('products', TestCase::DEFAULT_STORE_ID, true);
        $settings = $this->algoliaConnector->getSettings($tmpIndexOptions);
        $this->assertEquals(['sku'], $settings['disableTypoToleranceOnAttributes']);

        $queueRunner->executeFull();
        $queueRunner->executeFull();

        $settings = $this->algoliaConnector->getSettings($productionIndexOptions);
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
                'data' => '{"storeId":"1","entityIds":["9","22"]}',
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
                'data' => '{"storeId":"2","entityIds":["9","22"]}',
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
                'data' => '{"storeId":"3","entityIds":["9","22"]}',
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
                'data' => '{"storeId":"1","entityIds":["448"]}',
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
                'data' => '{"storeId":"2","entityIds":["448"]}',
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
                'data' => '{"storeId":"3","entityIds":["448"]}',
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
                'data' => '{"storeId":"1","entityIds":["40"]}',
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
                'data' => '{"storeId":"2","entityIds":["40"]}',
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
                'data' => '{"storeId":"3","entityIds":["40"]}',
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
                'data' => '{"storeId":"1","entityIds":["405"]}',
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
                'data' => '{"storeId":"2","entityIds":["405"]}',
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
                'data' => '{"storeId":"3","entityIds":["405"]}',
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
            'data' => '{"storeId":"1","entityIds":["9","22"]}',
            'max_retries' => '3',
            'retries' => '0',
            'error_log' => '',
            'data_size' => 3,
            'merged_ids' => ['1', '7'],
            'store_id' => '1',
            'is_full_reindex' => '0',
            'decoded_data' => [
                'storeId' => '1',
                'entityIds' => [
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
            'data' => '{"storeId":"1","entityIds":["448"]}',
            'max_retries' => '3',
            'retries' => '0',
            'error_log' => '',
            'data_size' => 2,
            'merged_ids' => ['4', '10'],
            'store_id' => '1',
            'is_full_reindex' => '0',
            'decoded_data' => [
                'storeId' => '1',
                'entityIds' => [
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
                'data' => '{"storeId":"1","entityIds":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 2,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"storeId":"2","entityIds":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 3,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"storeId":"3","entityIds":["9","22"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 2,
            ], [
                'job_id' => 4,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Helper\Data::class,
                'method' => 'deleteObjects',
                'data' => '{"storeId":"1","product_ids":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 5,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"storeId":"2","entityIds":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 6,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"storeId":"3","entityIds":["448"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 7,
                'pid' => null,
                'class' => IndicesConfigurator::class,
                'method' => 'saveConfigurationToAlgolia',
                'data' => '{"storeId":"1"}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 8,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"storeId":"2","entityIds":["40"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 9,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Model\IndexMover::class,
                'method' => 'moveIndexWithSetSettings',
                'data' => '{"storeId":"3"}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 10,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"storeId":"1","entityIds":["405"]}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 11,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Model\IndexMover::class,
                'method' => 'moveIndexWithSetSettings',
                'data' => '{"storeId":"2"}',
                'max_retries' => 3,
                'retries' => 0,
                'error_log' => '',
                'data_size' => 10,
            ], [
                'job_id' => 12,
                'pid' => null,
                'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"storeId":"3","entityIds":["405"]}',
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
                'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
                'method' => 'buildIndexList',
                'data' => '{"storeId":"1","entityIds":["9","22"]}',
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
                'data' => '{"storeId":"2","entityIds":["9","22"]}',
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
                'data' => '{"storeId":"3","entityIds":["9","22"]}',
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
                'data' => '{"storeId":"1","entityIds":["448"]}',
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
                'data' => '{"storeId":"2","entityIds":["448"]}',
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
                'data' => '{"storeId":"3","entityIds":["448"]}',
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
                'data' => '{"storeId":"1","entityIds":["40"]}',
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
                'data' => '{"storeId":"2","entityIds":["40"]}',
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
                'data' => '{"storeId":"3","entityIds":["40"]}',
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
                'data' => '{"storeId":"1","entityIds":["405"]}',
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
                'data' => '{"storeId":"2","entityIds":["405"]}',
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
                'data' => '{"storeId":"3","entityIds":["405"]}',
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
            'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
            'method' => 'buildIndexList',
            'data' => '{"storeId":"1","entityIds":["9","22"]}',
            'merged_ids' => ['1', '7'],
            'store_id' => '1',
            'decoded_data' => [
                'storeId' => '1',
                'entityIds' => [
                    0 => '9',
                    1 => '22',
                    2 => '40',
                ],
            ],
        ];

        $expectedLastJob = [
            'job_id' => '6',
            'pid' => $pid,
            'class' => \Algolia\AlgoliaSearch\Service\Product\IndexBuilder::class,
            'method' => 'buildIndexList',
            'data' => '{"storeId":"3","entityIds":["448"]}',
            'merged_ids' => ['6', '12'],
            'store_id' => '3',
            'decoded_data' => [
                'storeId' => '3',
                'entityIds' => [
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
        $this->setConfig(QueueHelper::NUMBER_OF_JOB_TO_RUN, 10);
        $this->setConfig(ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE, 100);

        $productIds = range(1, 5000);
        $jsonProductIds = json_encode($productIds);

        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');
        $this->connection->query('INSERT INTO `algoliasearch_queue` (`job_id`, `pid`, `class`, `method`, `data`, `max_retries`, `retries`, `error_log`, `data_size`) VALUES
            (1, NULL, \'class\', \'buildIndexList\', \'{"storeId":"1","entityIds":' . $jsonProductIds . '}\', 3, 0, \'\', 5000),
            (2, NULL, \'class\', \'buildIndexList\', \'{"storeId":"2","entityIds":["9","22"]}\', 3, 0, \'\', 2);');

        $pid = getmypid();
        /** @var Job[] $jobs */
        $jobs = $this->invokeMethod($this->queue, 'getJobs', ['maxJobs' => 10]);

        $this->assertEquals(2, count($jobs));

        $job = reset($jobs);
        $this->assertEquals(5000, $job->getDataSize());
        $this->assertEquals(5000, count($job->getDecodedData()['entityIds']));

        $dbJobs = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();

        $this->assertEquals(2, count($dbJobs));

        $firstJob = reset($dbJobs);
        $lastJob = end($dbJobs);

        $this->assertEquals($pid, $firstJob['pid']);
        $this->assertEquals($pid, $lastJob['pid']);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testMaxSingleJobSize()
    {
        // Default value - maxBatchSize = 1000
        $this->setConfig(QueueHelper::NUMBER_OF_JOB_TO_RUN, 10);
        $this->setConfig(ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE, 100);

        $productIds = range(1, 99);
        $jsonProductIds = json_encode($productIds);

        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');
        $this->connection->query('INSERT INTO `algoliasearch_queue` (`job_id`, `pid`, `class`, `method`, `data`, `max_retries`, `retries`, `error_log`, `data_size`) VALUES
            (1, NULL, \'class\', \'buildIndexList\', \'{"storeId":"1","entityIds":' . $jsonProductIds . '}\', 3, 0, \'\', 99),
            (2, NULL, \'class\', \'buildIndexList\', \'{"storeId":"2","entityIds":["9","22"]}\', 3, 0, \'\', 2);');

        $pid = getmypid();

        /** @var Job[] $jobs */
        $jobs = $this->invokeMethod($this->queue, 'getJobs', ['maxJobs' => 10]);

        $this->assertEquals(2, count($jobs));

        $firstJob = reset($jobs);
        $lastJob = end($jobs);

        $this->assertEquals(99, $firstJob->getDataSize());
        $this->assertEquals(99, count($firstJob->getDecodedData()['entityIds']));

        $this->assertEquals(2, $lastJob->getDataSize());
        $this->assertEquals(2, count($lastJob->getDecodedData()['entityIds']));

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
            QueueHelper::NUMBER_OF_JOB_TO_RUN,
            ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE,
        ]);

        $this->setConfig(QueueHelper::IS_ACTIVE, '1');

        $this->setConfig(QueueHelper::NUMBER_OF_JOB_TO_RUN, 10);
        $this->setConfig(ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE, 100);

        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        $productBatchQueueProcessor = $this->objectManager->get(ProductBatchQueueProcessor::class);
        $productBatchQueueProcessor->processBatch(1, range(1, 512));

        $dbJobs = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertSame(6, count($dbJobs));

        $firstJob = reset($dbJobs);
        $lastJob = end($dbJobs);

        $this->assertEquals(100, (int) $firstJob['data_size']);

        $this->assertEquals($this->assertValues->lastJobDataSize, (int) $lastJob['data_size']);
    }

    /**
     * Seeds a test job into the queue.
     *
     * The store_id column is always written explicitly (not only inside the payload JSON):
     * MAGE-1742 will make the column the source of truth for claiming, so the claim tests
     * must not depend on the payload fallback in Job::prepare().
     *
     * @param array $overrides column values on top of a valid buildIndexList job; pass
     *                         'store_id' => null for a legacy store-agnostic row
     */
    private function seedJob(array $overrides = []): void
    {
        $storeId = array_key_exists('store_id', $overrides) ? $overrides['store_id'] : 1;

        $row = array_merge([
            'created' => '2017-09-01 12:00:00',
            'pid' => null,
            'class' => \Algolia\AlgoliaSearch\Service\Category\IndexBuilder::class,
            'method' => 'buildIndexList',
            'data' => json_encode(['storeId' => (string) $storeId, 'entityIds' => ['9', '22']]),
            'max_retries' => 3,
            'retries' => 0,
            'error_log' => '',
            'data_size' => 2,
            'store_id' => $storeId,
            'is_full_reindex' => 0,
        ], $overrides);

        $this->connection->insert('algoliasearch_queue', $row);
    }

    /**
     * Raw queue rows as the DB stores them, ordered by insertion. Used to assert on the
     * claim side effects (pid and locked_at stamping) rather than on in-memory job objects,
     * which mirror the same values.
     *
     * @return array<int, array{job_id: string, store_id: ?string, pid: ?string, locked_at: ?string}>
     */
    private function fetchQueueRows(): array
    {
        return $this->connection->query(
            'SELECT job_id, store_id, pid, locked_at FROM algoliasearch_queue ORDER BY job_id'
        )->fetchAll();
    }

    /**
     * A claim returns merged jobs, where one job may cover several queue rows. This maps
     * the claim back to the individual job_id rows it locked, so assertions can reason
     * about which DB rows were claimed rather than how the claimer merged them.
     *
     * @param Job[] $jobs
     *
     * @return int[]
     */
    private function getClaimedRowIds(array $jobs): array
    {
        $rowIds = [];
        foreach ($jobs as $job) {
            $rowIds = array_merge($rowIds, $job->getMergedIds());
        }

        return array_map('intval', $rowIds);
    }

    /**
     * getStoreIdsWithPendingJobs() must only surface stores whose rows a worker can
     * actually claim: rows locked by another worker, rows that exhausted their retries,
     * and legacy store-agnostic rows (which store-scoped workers never claim) are hidden.
     */
    public function testGetStoreIdsWithPendingJobsReturnsOnlyClaimableStores()
    {
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');

        $this->seedJob(['store_id' => 1]);
        $this->seedJob(['store_id' => 2]);
        $this->seedJob(['store_id' => 3]);
        // Locked by another worker
        $this->seedJob(['store_id' => 4, 'pid' => 99999]);
        // Exhausted retries
        $this->seedJob(['store_id' => 5, 'retries' => 3]);
        // Legacy store-agnostic row
        $this->seedJob(['store_id' => null, 'data' => '{"entityIds":["9","22"]}']);

        $this->assertSame([1, 2, 3], $this->queue->getStoreIdsWithPendingJobs());
    }

    /**
     * A store-scoped claim reserves rows exclusively for one worker: the claiming worker
     * gets every pending row of its store (two same-store rows merge into one job here)
     * and the pid/locked_at stamping must not leak onto rows belonging to other stores,
     * which remain available for their own workers.
     */
    public function testStoreScopedClaimOnlyClaimsItsOwnStoreRows()
    {
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');
        $this->setConfig(ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE, 300);

        $this->seedJob(['store_id' => 1, 'data_size' => 1, 'data' => '{"storeId":"1","entityIds":["9"]}']);
        $this->seedJob(['store_id' => 2, 'data_size' => 1, 'data' => '{"storeId":"2","entityIds":["9"]}']);
        $this->seedJob(['store_id' => 1, 'data_size' => 1, 'data' => '{"storeId":"1","entityIds":["22"]}']);
        $this->seedJob(['store_id' => 2, 'data_size' => 1, 'data' => '{"storeId":"2","entityIds":["22"]}']);

        $storeOneRowIds = array_map('intval', array_column(
            array_filter($this->fetchQueueRows(), fn (array $row) => $row['store_id'] === '1'),
            'job_id'
        ));

        $jobs = $this->invokeMethod($this->queue, 'getJobs', ['maxJobs' => 10, 'storeId' => 1]);

        $this->assertCount(1, $jobs);
        $this->assertSame(1, (int) $jobs[0]->getStoreId());
        $this->assertSame($storeOneRowIds, $this->getClaimedRowIds($jobs));

        foreach ($this->fetchQueueRows() as $row) {
            if ($row['store_id'] === '1') {
                $this->assertNotNull($row['pid'], 'Claimed row must be stamped with a pid');
                $this->assertNotNull($row['locked_at'], 'Claimed row must be stamped with a lock time');
            } else {
                $this->assertNull($row['pid'], 'Other stores rows must stay unclaimed');
                $this->assertNull($row['locked_at'], 'Other stores rows must stay unlocked');
            }
        }
    }

    /**
     * Sequential equivalent of two workers racing: getJobs() stamps pid inside the claim
     * transaction, so once a claim commits its rows are invisible to every later claim
     * (pid IS NULL filter), regardless of which Queue instance issues it. 
     * maxJobs=1 yields one job per claim, and because the rows cannot merge, 
     * four claims drain the four seeded rows exactly once.
     */
    public function testScopedClaimsAreDisjointAcrossInstancesAndDrainPerStore()
    {
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');
        $this->setConfig(ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE, 300);

        // 200 entityIds per row: two rows would exceed the 300 batch size, so they never merge
        $manyIds = json_encode(['storeId' => '1', 'entityIds' => array_map('strval', range(1, 200))]);
        $manyIdsStoreTwo = json_encode(['storeId' => '2', 'entityIds' => array_map('strval', range(201, 400))]);

        $this->seedJob(['store_id' => 1, 'data_size' => 200, 'data' => $manyIds]);
        $this->seedJob(['store_id' => 1, 'data_size' => 200, 'data' => $manyIds]);
        $this->seedJob(['store_id' => 2, 'data_size' => 200, 'data' => $manyIdsStoreTwo]);
        $this->seedJob(['store_id' => 2, 'data_size' => 200, 'data' => $manyIdsStoreTwo]);

        $queueOne = $this->objectManager->create(Queue::class);
        $queueTwo = $this->objectManager->create(Queue::class);

        $claimStoreOneFirst = $this->invokeMethod($queueOne, 'getJobs', ['maxJobs' => 1, 'storeId' => 1]);
        $claimStoreTwo = $this->invokeMethod($queueTwo, 'getJobs', ['maxJobs' => 1, 'storeId' => 2]);
        $claimStoreOneRemaining = $this->invokeMethod($queueOne, 'getJobs', ['maxJobs' => 1, 'storeId' => 1]);
        $claimStoreTwoRemaining = $this->invokeMethod($queueTwo, 'getJobs', ['maxJobs' => 1, 'storeId' => 2]);

        $this->assertCount(1, $claimStoreOneFirst);
        $this->assertCount(1, $claimStoreTwo);
        $this->assertCount(1, $claimStoreOneRemaining);
        $this->assertCount(1, $claimStoreTwoRemaining);

        $rowIdsStoreOneFirst = $this->getClaimedRowIds($claimStoreOneFirst);
        $rowIdsStoreTwo = $this->getClaimedRowIds($claimStoreTwo);
        $rowIdsStoreOneRemaining = $this->getClaimedRowIds($claimStoreOneRemaining);
        $rowIdsStoreTwoRemaining = $this->getClaimedRowIds($claimStoreTwoRemaining);

        $this->assertEmpty(
            array_intersect($rowIdsStoreOneFirst, $rowIdsStoreOneRemaining),
            'A second claim on the same store must only return the remaining rows'
        );
        $this->assertEmpty(
            array_intersect($rowIdsStoreTwo, $rowIdsStoreTwoRemaining),
            'A second claim on the same store must only return the remaining rows'
        );
        $this->assertEmpty(
            array_intersect($rowIdsStoreOneFirst, $rowIdsStoreTwo),
            'Claims on different stores must be disjoint'
        );

        $claimedRowIds = array_merge(
            $rowIdsStoreOneFirst,
            $rowIdsStoreOneRemaining,
            $rowIdsStoreTwo,
            $rowIdsStoreTwoRemaining
        );
        $this->assertSame(
            array_column($this->fetchQueueRows(), 'job_id'),
            array_map('strval', $claimedRowIds),
            'Together the claims must have covered every seeded row exactly once'
        );
    }

    /**
     * Regression guard for the sequential contract: without a store ID the claim must
     * behave exactly as before this change and reserve pending rows across all stores.
     */
    public function testUnfilteredClaimStillSpansStores()
    {
        $this->connection->query('TRUNCATE TABLE algoliasearch_queue');
        $this->setConfig(ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE, 300);

        $this->seedJob(['store_id' => 1]);
        $this->seedJob(['store_id' => 2]);

        $jobs = $this->invokeMethod($this->queue, 'getJobs', ['maxJobs' => 10]);

        $this->assertCount(2, $jobs);
        $this->assertEqualsCanonicalizing(['1', '2'], array_map(fn (Job $job) => (string) $job->getStoreId(), $jobs));
    }
}
