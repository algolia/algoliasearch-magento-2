<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Search;

use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Indexer\Product;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;
use Magento\Framework\Exception\NoSuchEntityException;

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
        $query = 'bag';
        $results = $this->search($query);
        $result = $this->getFirstResult($results);
        // Search returns result
        $this->assertNotEmpty($result, "Query didn't bring result");

        $product = $this->objectManager->create(\Magento\Catalog\Model\Product::class);
        $product->load($result['entity_id']);
        // Result exists in DB
        $this->assertNotEmpty($product->getName(), "Query result item couldn't find in the DB");
        // Query word exists title
        $this->assertStringContainsString($query, strtolower($product->getName()), "Query word doesn't exist in product name");
    }

    public function testSearchBySku()
    {
        $sku = "24-MB01";
        $results = $this->search($sku);
        $result = $this->getFirstResult($results);
        // Search by SKU returns result
        $this->assertNotEmpty($result, "SKU search didn't bring result");

        $product = $this->objectManager->create(\Magento\Catalog\Model\Product::class);
        $product->load($result['entity_id']);
        // Result exists in DB
        $this->assertNotEmpty($product->getSku(), "SKU search result item couldn't find in the DB");
        // Query word exists title
        $this->assertEquals($sku, $product->getSku(), "Query SKU doesn't match with product SKU");
    }

    public function testCategorySearch()
    {
        // Get products by categoryId
        $results = $this->search('', 1, [
            'facetFilters' => ['categoryIds:' . $this->assertValues->expectedCategory]
        ]);
        $result = $this->getFirstResult($results);
        // Category filter returns result
        $this->assertNotEmpty($result, "Category filter didn't return result");
    }

    /**
     * @param array $results
     * @return array
     */
    protected function getFirstResult(array $results): array
    {
        list($results, $totalHits, $facetsFromAlgolia) = $results;
        return array_shift($results);
    }

    /**
     * @param string $query
     * @param int $storeId
     * @return array
     */
    protected function search(string $query = '', int $storeId = 1, array $params = []): array
    {
        try {
            return $this->helper->getSearchResult($query, $storeId, $params);
        } catch (NoSuchEntityException $e) {
            return [];
        }
    }
}
