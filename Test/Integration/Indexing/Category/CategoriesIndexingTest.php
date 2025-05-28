<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Category;

use Algolia\AlgoliaSearch\Console\Command\Indexer\IndexCategoriesCommand;
use Algolia\AlgoliaSearch\Model\Indexer\Category as CategoryIndexer;
use Algolia\AlgoliaSearch\Service\Category\BatchQueueProcessor as CategoryBatchQueueProcessor;
use Algolia\AlgoliaSearch\Test\Integration\Indexing\IndexingTestCase;

class CategoriesIndexingTest extends IndexingTestCase
{
    public function testCategories()
    {
        $categoryBatchQueueProcessor = $this->objectManager->get(CategoryBatchQueueProcessor::class);
        $this->processTest(
            $categoryBatchQueueProcessor,
            'categories',
            $this->assertValues->expectedCategory
        );
    }

    public function testDefaultIndexableAttributes()
    {
        $this->setConfig(
            'algoliasearch_categories/categories/category_additional_attributes',
            $this->getSerializer()->serialize([])
        );

        $categoryBatchQueueProcessor = $this->objectManager->get(CategoryBatchQueueProcessor::class);
        $categoryBatchQueueProcessor->processBatch(1, [3]);
        $this->algoliaConnector->waitLastTask();

        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($this->indexPrefix . 'default_categories');
        $results = $this->algoliaConnector->getObjects($indexOptions, ['3']);
        $hit = reset($results['results']);

        $defaultAttributes = [
            'objectID',
            'name',
            'url',
            'path',
            'level',
            'include_in_menu',
            '_tags',
            'popularity',
            'algoliaLastUpdateAtCET',
            'product_count',
        ];

        foreach ($defaultAttributes as $key => $attribute) {
            $this->assertTrue(key_exists($attribute, $hit), 'Category attribute "' . $attribute . '" should be indexed but it is not"');
            unset($hit[$attribute]);
        }

        $extraAttributes = implode(', ', array_keys($hit));
        $this->assertTrue(empty($hit), 'Extra category attributes (' . $extraAttributes . ') are indexed and should not be.');
    }

    public function testIndexingCategoriesCommand()
    {
        $indexCategoriesCmd = $this->objectManager->get(IndexCategoriesCommand::class);
        $this->processCommandTest($indexCategoriesCmd,'categories', $this->assertValues->expectedCategory);
    }

    /**
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/full_indexing/categories 0
     */
    public function testDisabledOldIndexer()
    {
        $categoriesIndexer = $this->objectManager->create(CategoryIndexer::class);
        $this->processOldIndexerTest($categoriesIndexer, 'categories', 0);
    }

    /**
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/full_indexing/categories 1
     */
    public function testEnabledOldIndexer()
    {
        $categoriesIndexer = $this->objectManager->create(CategoryIndexer::class);
        $this->processOldIndexerTest($categoriesIndexer, 'categories', $this->assertValues->expectedCategory);
    }
}
