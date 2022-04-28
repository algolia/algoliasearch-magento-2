<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Indexer\Product;

class SearchTest extends TestCase
{
    public function testSearch()
    {
        /** @var Product $indexer */
        $indexer = $this->getObjectManager()->create(Product::class);
        $indexer->executeFull();

        $this->algoliaHelper->waitLastTask();

        /** @var Data $helper */
        $helper = $this->getObjectManager()->create(Data::class);
        list($results, $totalHits, $facetsFromAlgolia) = $helper->getSearchResult('', 1);

        $this->assertNotEmpty($results);
    }
}
