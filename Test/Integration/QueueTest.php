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
        $queue->run(2);

        $this->algoliaHelper->waitLastTask();

        $indices = $this->algoliaHelper->listIndexes();

        $existsDefaultTmpIndex = false;
        foreach ($indices['items'] as $index) {
            if ($index['name'] === $this->indexPrefix.'default_products_tmp') {
                $existsDefaultTmpIndex = true;
            }
        }

        $this->assertTrue($existsDefaultTmpIndex, 'Default products production index does not exists and it should');

        // Run the second two jobs - batch, move
        $queue->run(2);

        $this->algoliaHelper->waitLastTask();

        $indices = $this->algoliaHelper->listIndexes();

        $existsDefaultProdIndex = false;
        $existsDefaultTmpIndex = false;
        foreach ($indices['items'] as $index) {
            if ($index['name'] === $this->indexPrefix.'default_products') {
                $existsDefaultProdIndex = true;
            }

            if ($index['name'] === $this->indexPrefix.'default_products_tmp') {
                $existsDefaultTmpIndex = true;
            }

        }

        $this->assertFalse($existsDefaultTmpIndex, 'Default product TMP index exists and it should not'); // Was already moved
        $this->assertTrue($existsDefaultProdIndex, 'Default product production index does not exists and it should');

        $rows = $this->connection->query('SELECT * FROM algoliasearch_queue')->fetchAll();
        $this->assertEquals(0, count($rows));
    }
}
