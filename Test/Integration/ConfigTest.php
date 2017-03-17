<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Helper\Data;

class ConfigTest extends TestCase
{
    public function testFacets()
    {
        $facets = $this->configHelper->getFacets();

        /** @var Data $helper */
        $helper = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Helper\Data');
        $helper->saveConfigurationToAlgolia(1);

        $this->algoliaHelper->waitLastTask();

        $indexSettings = $this->algoliaHelper->getIndex($this->indexPrefix.'default_products')->getSettings();

        $this->assertEquals(count($facets), count($indexSettings['attributesForFaceting']));

        $attributesMatched = 0;
        foreach ($facets as $facet) {
            foreach ($indexSettings['attributesForFaceting'] as $indexFacet) {
                if ($facet['attribute'] === 'price' && strpos($indexFacet, 'price.') === 0) {
                    $attributesMatched++;
                } elseif ($facet['attribute'] === $indexFacet) {
                    $attributesMatched++;
                }
            }
        }

        $this->assertEquals(count($facets), $attributesMatched);
    }

    public function testAutomaticalSetOfCategoriesFacet()
    {
        /** @var Data $helper */
        $helper = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Helper\Data');

        // Remove categories from facets
        $facets = $this->configHelper->getFacets();
        foreach ($facets as $key => $facet) {
            if($facet['attribute'] === 'categories') {
                unset($facets[$key]);
                break;
            }
        }

        $this->setConfig('algoliasearch_instant/instant/facets', serialize($facets));

        // Set don't replace category pages with Algolia - categories attribute shouldn't be included in facets
        $this->setConfig('algoliasearch_instant/instant/replace_categories', '0');

        $helper->saveConfigurationToAlgolia(1);

        $this->algoliaHelper->waitLastTask();

        $indexSettings = $this->algoliaHelper->getIndex($this->indexPrefix.'default_products')->getSettings();

        $this->assertEquals(2, count($indexSettings['attributesForFaceting']));

        $categoriesAttributeIsIncluded = false;
        foreach ($indexSettings['attributesForFaceting'] as $attribute) {
            if ($attribute === 'categories') {
                $categoriesAttributeIsIncluded = true;
                break;
            }
        }

        $this->assertFalse($categoriesAttributeIsIncluded, 'Categories attribute should not be included in facets, but it is');

        // Set replace category pages with Algolia - categories attribute should be included in facets
        $this->setConfig('algoliasearch_instant/instant/replace_categories', '1');

        $helper->saveConfigurationToAlgolia(1);

        $this->algoliaHelper->waitLastTask();

        $indexSettings = $this->algoliaHelper->getIndex($this->indexPrefix.'default_products')->getSettings();

        $this->assertEquals(3, count($indexSettings['attributesForFaceting']));

        $categoriesAttributeIsIncluded = false;
        foreach ($indexSettings['attributesForFaceting'] as $attribute) {
            if ($attribute === 'categories') {
                $categoriesAttributeIsIncluded = true;
                break;
            }
        }

        $this->assertTrue($categoriesAttributeIsIncluded, 'Categories attribute should be included in facets, but it is not');
    }
}
