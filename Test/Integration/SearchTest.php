<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Indexer\Product;

class SearchTest extends TestCase
{
    public function testSearch()
    {
        /** @var Product $indexer */
        $indexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Product');
        $indexer->executeFull();

        $this->algoliaHelper->waitLastTask();

        /** @var Data $helper */
        $helper = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Helper\Data');
        $results = $helper->getSearchResult('', 1);

        $this->assertNotEmpty($results);
    }
}
