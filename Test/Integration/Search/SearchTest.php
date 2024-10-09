<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Search;

use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Indexer\Product;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;

class SearchTest extends TestCase
{
    /** @var Product */
    protected $productIndexer;

    /** @var Data */
    protected $helper;

    public function setUp(): void
    {
        parent::setUp();

        $this->productIndexer = $this->objectManager->get(Product::class);
        $this->helper = $this->getObjectManager()->create(Data::class);

        $this->productIndexer->executeFull();
        $this->algoliaHelper->waitLastTask();
    }

    public function testSearch()
    {
        list($results, $totalHits, $facetsFromAlgolia) = $this->helper->getSearchResult('', 1);
        $this->assertNotEmpty($results);
    }

    public function testCategorySearch()
    {
        list($results, $totalHits, $facetsFromAlgolia) = $this->helper->getSearchResult('', 1, [
            'facetFilters' => ['categoryIds:' . $this->assertValues->expectedCategory]
        ]);
        $this->assertNotEmpty($results);
    }
}
