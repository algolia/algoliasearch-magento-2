<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Category;

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
        $this->algoliaHelper->waitLastTask();

        $results = $this->algoliaHelper->getObjects($this->indexPrefix . 'default_categories', ['3']);
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
}
