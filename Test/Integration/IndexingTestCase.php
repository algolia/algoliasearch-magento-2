<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Magento\Framework\Indexer\ActionInterface;

abstract class IndexingTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setConfig('algoliasearch_queue/queue/active', '0');
    }

    protected function processTest(ActionInterface $indexer, $indexSuffix, $expectedNbHits)
    {
        $this->algoliaHelper->clearIndex($this->indexPrefix . 'default_' . $indexSuffix);

        $indexer->executeFull();

        $this->algoliaHelper->waitLastTask();

        $resultsDefault = $this->algoliaHelper->query($this->indexPrefix . 'default_' . $indexSuffix, '', []);

        $this->assertEquals($expectedNbHits, $resultsDefault['results'][0]['nbHits']);
    }

    /**
     * @param string $indexName
     * @param string $recordId
     * @param array $expectedValues
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function assertAlgoliaRecordValues(
        string $indexName,
        string $recordId,
        array $expectedValues,
        int $storeId = null
    ) : void {
        $res = $this->algoliaHelper->getObjects($indexName, [$recordId], $storeId);
        $record = reset($res['results']);
        foreach ($expectedValues as $attribute => $expectedValue) {
            $this->assertEquals($expectedValue, $record[$attribute]);
        }
    }
}
