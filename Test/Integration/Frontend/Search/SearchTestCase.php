<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Frontend\Search;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Test\Integration\IndexCleaner;
use Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\ProductsIndexingTest;
use Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\ProductsIndexingTestCase;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Testing searches requires that products first be indexed to Algolia.
 * However, it is only necessary to index *once per class* for all tests in the class.
 * This class provides a method to achieve that.
 */
class SearchTestCase extends ProductsIndexingTestCase
{
    /**
     * This is a class level index prefix.
     * It is used to identify the index for all tests in the class.
     */
    protected static string $testSuiteIndexPrefix;

    /**
     * Reindex *once* for reuse across multiple tests.
     * Provides a alternative to `setUpBeforeClass`
     * Simulates a class level operation in a non-static context
     *
     * @param string $key - a unique key for the operation
     * @return void
     * @throws AlgoliaException
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    protected function indexOncePerClass(string $key): void
    {
        $this->setupTestSuiteIndexPrefix();

        $this->runOnce(function() {
            $this->indexAllProducts();
        }, $key);
    }

    /**
     * @throws NoSuchEntityException|AlgoliaException
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$testSuiteIndexPrefix ?? false) {
            IndexCleaner::clean(self::$testSuiteIndexPrefix);
        }
    }

    /**
     * Removes timestamp from index prefix for index reuse.
     * For expected format see:
     * @see \Algolia\AlgoliaSearch\Test\Integration\TestCase::bootstrap
     */
    protected function setupTestSuiteIndexPrefix(): void
    {
        $this->indexPrefix = $this->simplifyIndexPrefix($this->indexPrefix);
        self::$testSuiteIndexPrefix = $this->indexPrefix; // Clear after all tests
        $this->setConfig('algoliasearch_credentials/credentials/index_prefix', $this->indexPrefix);
    }

    /**
     * In order to reuse the same index across tests strip the timestamp
     */
    protected function simplifyIndexPrefix(string $indexPrefix): string
    {
        $parts = explode('_', $this->indexPrefix);
        unset($parts[2]); // kill the timestamp
        return implode('_', array_values($parts));
    }

    /**
     * Index all products for a given store based on the current configuration.
     *
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws DiagnosticsException
     */
    protected function indexAllProducts(int $storeId = 1): void
    {
        $this->productBatchQueueProcessor->processBatch($storeId);
        $this->algoliaConnector->waitLastTask();
    }
}
