<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing;

use Algolia\AlgoliaSearch\Api\Processor\BatchQueueProcessorInterface;
use Algolia\AlgoliaSearch\Console\Command\Indexer\AbstractIndexerCommand;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;
use Magento\Framework\Indexer\ActionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class IndexingTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setConfig('algoliasearch_queue/queue/active', '0');
    }

    protected function processTest(
        BatchQueueProcessorInterface $batchQueueProcessor,
        $indexSuffix,
        $expectedNbHits
    ) {
        $this->algoliaHelper->clearIndex($this->indexPrefix . 'default_' . $indexSuffix);

        $batchQueueProcessor->processBatch(1);
        $this->algoliaHelper->waitLastTask();

        $this->assertNumberofHits($indexSuffix, $expectedNbHits);
    }

    protected function processOldIndexerTest(ActionInterface $indexer, $indexSuffix, $expectedNbHits)
    {
        $this->algoliaHelper->clearIndex($this->indexPrefix . 'default_' . $indexSuffix);

        $indexer->executeFull();
        $this->algoliaHelper->waitLastTask();

        $this->assertNumberofHits($indexSuffix, $expectedNbHits);
    }

    protected function processCommandTest(
        AbstractIndexerCommand $command,
        $indexSuffix,
        $expectedNbHits
    ) {
        $this->algoliaHelper->clearIndex($this->indexPrefix . 'default_' . $indexSuffix);

        $this->mockProperty($command, 'output', OutputInterface::class);
        $this->invokeMethod(
            $command,
            'execute',
            [
                $this->createMock(InputInterface::class),
                $this->createMock(OutputInterface::class)
            ]
        );
        $this->algoliaHelper->waitLastTask();

        $this->assertNumberofHits($indexSuffix, $expectedNbHits);
    }

    protected function assertNumberofHits($indexSuffix, $expectedNbHits)
    {
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
