<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Model\IndexOptions;
use Magento\Framework\Exception\NoSuchEntityException;
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

        $indexOptions = new IndexOptions([
            IndexOptionsInterface::ENFORCED_INDEX_NAME => $this->indexPrefix . 'default_' . $indexSuffix
        ]);

        $resultsDefault = $this->algoliaHelper->query($indexOptions, '', []);

        $this->assertEquals($expectedNbHits, $resultsDefault['results'][0]['nbHits']);
    }

    /**
     * @param IndexOptionsInterface $indexOptions
     * @param string $recordId
     * @param array $expectedValues
     * @return void
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    public function assertAlgoliaRecordValues(
        IndexOptionsInterface $indexOptions,
        string $recordId,
        array $expectedValues,
    ) : void {
        $res = $this->algoliaHelper->getObjects($indexOptions, [$recordId]);
        $record = reset($res['results']);
        foreach ($expectedValues as $attribute => $expectedValue) {
            $this->assertEquals($expectedValue, $record[$attribute]);
        }
    }
}
