<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Model\Indexer\Category;

class CategoriesIndexingTest extends IndexingTestCase
{
    public function testCategories()
    {
        /** @var Category $categoriesIndexer */
        $categoriesIndexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Category');
        $this->processTest($categoriesIndexer, 'categories', 18);
    }

    public function testDefaultIndexableAttributes()
    {
        $this->setConfig('algoliasearch_categories/categories/category_additional_attributes', serialize([]));

        /** @var Category $categoriesIndexer */
        $categoriesIndexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Category');
        $categoriesIndexer->executeFull();

        $this->algoliaHelper->waitLastTask();

        $results = $this->algoliaHelper->query($this->indexPrefix.'default_categories', '', array('hitsPerPage' => 1));
        $hit = reset($results['hits']);

        $defaultAttributes = array(
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
            '_highlightResult',
        );

        foreach ($defaultAttributes as $key => $attribute) {
            $this->assertTrue(key_exists($attribute, $hit), 'Category attribute "'.$attribute.'" should be indexed but it is not"');
            unset($hit[$attribute]);
        }

        $extraAttributes = implode(', ', array_keys($hit));
        $this->assertTrue(empty($hit), 'Extra category attributes ('.$extraAttributes.') are indexed and should not be.');
    }
}
