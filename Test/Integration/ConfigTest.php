<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Helper\Data;
use AlgoliaSearch\AlgoliaException;

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

    public function testRetrievableAttributes()
    {
        $this->resetConfigs(array('algoliasearch_products/products/product_additional_attributes', 'algoliasearch_categories/categories/category_additional_attributes'));

        $this->setConfig('algoliasearch_advanced/advanced/customer_groups_enable', '0');

        $retrievableAttributes = $this->configHelper->getAttributesToRetrieve(1);
        $this->assertEmpty($retrievableAttributes);

        $this->setConfig('algoliasearch_advanced/advanced/customer_groups_enable', '1');

        $retrievableAttributes = $this->configHelper->getAttributesToRetrieve(1);
        $this->assertNotEmpty($retrievableAttributes);

        $retrievableAttributes = $retrievableAttributes['attributesToRetrieve'];
        $this->assertNotEmpty($retrievableAttributes);

        $this->assertContains('objectID', $retrievableAttributes);
        $this->assertContains('name', $retrievableAttributes);
        $this->assertContains('product_count', $retrievableAttributes); // Category attribute
    }

    public function testReplicaCreationWithoutCustomerGroups()
    {
        $this->replicaCreationTest(false);
    }

    public function testReplicaCreationWithCustomerGroups()
    {
        $this->replicaCreationTest(true);
    }

    private function replicaCreationTest($withCustomerGroups = false)
    {
        $enableCustomGroups = '0';
        $priceAttribute = 'default';

        if ($withCustomerGroups === true) {
            $enableCustomGroups = '1';
            $priceAttribute = 'group_3';
        }

        $sortingIndicesData =
        [
            [
                'attribute' => 'price',
                'sort' => 'asc',
                'label' => 'Lowest price',
            ],
            [
                'attribute' => 'price',
                'sort' => 'desc',
                'label' => 'Highest price',
            ],
            [
                'attribute' => 'created_at',
                'sort' => 'desc',
                'label' => 'Newest first',
            ],
        ];

        $this->setConfig('algoliasearch_credentials/credentials/is_instant_enabled', '1'); // Needed to set replicas to Algolia
        $this->setConfig('algoliasearch_instant/instant/sorts', serialize($sortingIndicesData));
        $this->setConfig('algoliasearch_advanced/advanced/customer_groups_enable', $enableCustomGroups);

        $sortingIndicesWithRankingWhichShouldBeCreated = [
            $this->indexPrefix.'default_products_price_'.$priceAttribute.'_asc' => 'asc(price.USD.'.$priceAttribute.')',
            $this->indexPrefix.'default_products_price_'.$priceAttribute.'_desc' => 'desc(price.USD.'.$priceAttribute.')',
            $this->indexPrefix.'default_products_created_at_desc' => 'desc(created_at)',
        ];

        /** @var Data $helper */
        $helper = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Helper\Data');
        $helper->saveConfigurationToAlgolia(1);

        $this->algoliaHelper->waitLastTask();

        $indices = $this->algoliaHelper->listIndexes();
        $indicesNames = array_map(function($indexData) {
            return $indexData['name'];
        }, $indices['items']);

        foreach ($sortingIndicesWithRankingWhichShouldBeCreated as $indexName => $firstRanking) {
            $this->assertContains($indexName, $indicesNames);

            $settings = $this->algoliaHelper->getSettings($indexName);
            $this->assertEquals($firstRanking, reset($settings['ranking']));
        }
    }

    public function testExtraSettings()
    {
        /** @var Data $helper */
        $helper = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Helper\Data');

        $helper->saveConfigurationToAlgolia(1);
        $this->algoliaHelper->waitLastTask();

        $sections = array('products', 'categories', 'pages', 'suggestions');

        foreach ($sections as $section) {
            $indexName = $this->indexPrefix.'default_'.$section;

            $this->algoliaHelper->setSettings($indexName, array('exactOnSingleWordQuery' => 'attribute'));
        }

        $this->algoliaHelper->waitLastTask();

        foreach ($sections as $section) {
            $indexName = $this->indexPrefix.'default_'.$section;

            $currentSettings = $this->algoliaHelper->getIndex($indexName)->getSettings();

            $this->assertArrayHasKey('exactOnSingleWordQuery', $currentSettings);
            $this->assertEquals('attribute', $currentSettings['exactOnSingleWordQuery']);
        }

        foreach ($sections as $section) {
            $this->setConfig('algoliasearch_extra_settings/extra_settings/'.$section.'_extra_settings', '{"exactOnSingleWordQuery":"word"}');
        }

        $helper->saveConfigurationToAlgolia(1);
        $this->algoliaHelper->waitLastTask();

        foreach ($sections as $section) {
            $indexName = $this->indexPrefix.'default_'.$section;

            $currentSettings = $this->algoliaHelper->getIndex($indexName)->getSettings();

            $this->assertArrayHasKey('exactOnSingleWordQuery', $currentSettings);
            $this->assertEquals('word', $currentSettings['exactOnSingleWordQuery']);
        }
    }

    public function testInvalidExtraSettings()
    {
        /** @var Data $helper */
        $helper = $this->getObjectManager()->create('Algolia\AlgoliaSearch\Helper\Data');

        $sections = array('products', 'categories', 'pages', 'suggestions');

        foreach ($sections as $section) {
            $this->setConfig('algoliasearch_extra_settings/extra_settings/'.$section.'_extra_settings', '{"foo":"bar"}');
        }

        try {
            $helper->saveConfigurationToAlgolia(1);
        } catch(AlgoliaException $e) {
            $message = $e->getMessage();

            // Check if the error message contains error for all sections
            foreach ($sections as $section) {
                $position = strpos($message, $section);
                $this->assertTrue($position !== false);
            }

            return;
        }

        $this->fail('AlgoliaException was not raised');
    }
}
