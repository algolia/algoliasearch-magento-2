<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Magento\Framework\Indexer\ActionInterface;

abstract class IndexingTestCase extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->setConfig('algoliasearch_queue/queue/active', '0');
    }

    protected function processTest(ActionInterface $indexer, $indexSuffix, $expectedNbHits)
    {
        $this->algoliaHelper->clearIndex($this->indexPrefix.'default_'.$indexSuffix);

        $indexer->executeFull();

        $this->algoliaHelper->waitLastTask();

        $resultsDefault = $this->algoliaHelper->query($this->indexPrefix.'default_'.$indexSuffix, '', array());

        $this->assertEquals($expectedNbHits, $resultsDefault['nbHits']);
    }
}
