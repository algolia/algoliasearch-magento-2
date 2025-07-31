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
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex(
            $this->indexPrefix . 'default_' . $indexSuffix
        );

        $this->algoliaConnector->clearIndex($indexOptions);

        $batchQueueProcessor->processBatch(1);
        $this->algoliaConnector->waitLastTask();

        $this->assertNumberofHits($indexSuffix, $expectedNbHits);
    }

    protected function processOldIndexerTest(ActionInterface $indexer, $indexSuffix, $expectedNbHits)
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex(
            $this->indexPrefix . 'default_' . $indexSuffix
        );

        $this->algoliaConnector->clearIndex($indexOptions);

        $indexer->executeFull();
        $this->algoliaConnector->waitLastTask();

        $this->assertNumberofHits($indexSuffix, $expectedNbHits);
    }

    protected function processCommandTest(
        AbstractIndexerCommand $command,
        $indexSuffix,
        $expectedNbHits
    ) {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex(
            $this->indexPrefix . 'default_' . $indexSuffix
        );

        $this->algoliaConnector->clearIndex($indexOptions);

        $this->mockProperty($command, 'output', OutputInterface::class);
        $this->invokeMethod(
            $command,
            'execute',
            [
                $this->createMock(InputInterface::class),
                $this->createMock(OutputInterface::class)
            ]
        );
        $this->algoliaConnector->waitLastTask();

        $this->assertNumberofHits($indexSuffix, $expectedNbHits);
    }

    protected function assertNumberofHits($indexSuffix, $expectedNbHits)
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex(
            $this->indexPrefix . 'default_' . $indexSuffix
        );

        $resultsDefault = $this->algoliaConnector->query($indexOptions, '', []);
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
        ?int $storeId = null
    ) : void {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $res = $this->algoliaConnector->getObjects($indexOptions, [$recordId]);
        $record = reset($res['results']);
        foreach ($expectedValues as $attribute => $expectedValue) {
            $this->assertEquals($expectedValue, $record[$attribute]);
        }
    }
}
