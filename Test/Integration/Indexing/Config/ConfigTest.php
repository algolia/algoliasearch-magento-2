<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Config;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;

class ConfigTest extends TestCase
{
    public function testFacets()
    {
        /** @var IndicesConfigurator $indicesConfigurator */
        $indicesConfigurator = $this->getObjectManager()->create(IndicesConfigurator::class);
        $indicesConfigurator->saveConfigurationToAlgolia(1);

        $this->algoliaConnector->waitLastTask();

        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($this->indexPrefix . 'default_products');
        $indexSettings = $this->algoliaConnector->getSettings($indexOptions);

        $this->assertEquals($this->assertValues->attributesForFaceting, count($indexSettings['attributesForFaceting']));
    }

    public function testQueryRules()
    {
        /** @var IndicesConfigurator $indicesConfigurator */
        $indicesConfigurator = $this->getObjectManager()->create(IndicesConfigurator::class);
        $indicesConfigurator->saveConfigurationToAlgolia(1);

        $this->algoliaConnector->waitLastTask();

        $client = $this->algoliaConnector->getClient();

        $matchedRules = [];

        $hitsPerPage = 100;
        $page = 0;
        do {
            $fetchedQueryRules = $client->searchRules(
                $this->indexPrefix . 'default_products',
                [
                    'query'       => '',
                    'context'     => 'magento_filters',
                    'page'        => $page,
                    'hitsPerPage' => $hitsPerPage,
                ]
            );

            foreach ($fetchedQueryRules['hits'] as $hit) {
                $matchedRules[] = $hit;
            }

            $page++;
        } while (($page * $hitsPerPage) < $fetchedQueryRules['nbHits']);

        $this->assertEquals(0, count($matchedRules));
    }

    public function testAutomaticalSetOfCategoriesFacet()
    {
        // Removed test, the addition/deletion of the "categories" attribute is now checked by the FacetBuilder unit test
    }

    public function testRetrievableAttributes()
    {
        $this->resetConfigs(['algoliasearch_products/products/product_additional_attributes', 'algoliasearch_categories/categories/category_additional_attributes']);

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
        $this->assertContains('path', $retrievableAttributes); // Category attribute
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
                'sortLabel' => 'Lowest price',
            ],
            [
                'attribute' => 'price',
                'sort' => 'desc',
                'sortLabel' => 'Highest price',
            ],
            [
                'attribute' => 'created_at',
                'sort' => 'desc',
                'sortLabel' => 'Newest first',
            ],
        ];

        $this->setConfig('algoliasearch_instant/instant/is_instant_enabled', '1'); // Needed to set replicas to Algolia
        $this->setConfig('algoliasearch_instant/instant_sorts/sorts', $this->getSerializer()->serialize($sortingIndicesData));
        $this->setConfig('algoliasearch_advanced/advanced/customer_groups_enable', $enableCustomGroups);

        $sortingIndicesWithRankingWhichShouldBeCreated = [
            $this->indexPrefix . 'default_products_price_' . $priceAttribute . '_asc' => 'asc(price.USD.' . $priceAttribute . ')',
            $this->indexPrefix . 'default_products_price_' . $priceAttribute . '_desc' => 'desc(price.USD.' . $priceAttribute . ')',
            $this->indexPrefix . 'default_products_created_at_desc' => 'desc(created_at)',
        ];

        /** @var IndicesConfigurator $indicesConfigurator */
        $indicesConfigurator = $this->getObjectManager()->create(IndicesConfigurator::class);
        $indicesConfigurator->saveConfigurationToAlgolia(1);

        $this->algoliaConnector->waitLastTask();

        $indices = $this->algoliaConnector->listIndexes();
        $indicesNames = array_map(fn($indexData) => $indexData['name'], $indices['items']);

        foreach ($sortingIndicesWithRankingWhichShouldBeCreated as $indexName => $firstRanking) {
            $this->assertContains($indexName, $indicesNames);

            $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName);
            $settings = $this->algoliaConnector->getSettings($indexOptions);
            $this->assertEquals($firstRanking, reset($settings['ranking']));
        }
    }

    public function testExtraSettings()
    {
        /** @var IndicesConfigurator $indicesConfigurator */
        $indicesConfigurator = $this->getObjectManager()->create(IndicesConfigurator::class);

        $indicesConfigurator->saveConfigurationToAlgolia(1);
        $this->algoliaConnector->waitLastTask();

        $sections = ['products', 'categories', 'pages', 'suggestions'];

        foreach ($sections as $section) {
            $indexName = $this->indexPrefix . 'default_' . $section;
            $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName);

            $this->algoliaConnector->setSettings($indexOptions, ['exactOnSingleWordQuery' => 'attribute']);
            $this->algoliaConnector->waitLastTask();
        }

        foreach ($sections as $section) {
            $indexName = $this->indexPrefix . 'default_' . $section;
            $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName);
            $currentSettings = $this->algoliaConnector->getSettings($indexOptions);

            $this->assertArrayHasKey('exactOnSingleWordQuery', $currentSettings);
            $this->assertEquals('attribute', $currentSettings['exactOnSingleWordQuery']);
        }

        foreach ($sections as $section) {
            $this->setConfig('algoliasearch_extra_settings/extra_settings/' . $section . '_extra_settings', '{"exactOnSingleWordQuery":"word"}');
        }

        $indicesConfigurator->saveConfigurationToAlgolia(1);
        $this->algoliaConnector->waitLastTask();

        foreach ($sections as $section) {
            $indexName = $this->indexPrefix . 'default_' . $section;

            $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName);
            $currentSettings = $this->algoliaConnector->getSettings($indexOptions);

            $this->assertArrayHasKey('exactOnSingleWordQuery', $currentSettings);
            $this->assertEquals('word', $currentSettings['exactOnSingleWordQuery']);
        }
    }

    public function testInvalidExtraSettings()
    {
        /** @var IndicesConfigurator $indicesConfigurator */
        $indicesConfigurator = $this->getObjectManager()->create(IndicesConfigurator::class);

        $sections = ['products', 'categories', 'pages', 'suggestions'];

        foreach ($sections as $section) {
            $this->setConfig('algoliasearch_extra_settings/extra_settings/' . $section . '_extra_settings', '{"foo":"bar"}');
        }

        try {
            $indicesConfigurator->saveConfigurationToAlgolia(1);
        } catch (AlgoliaException $e) {
            $message = $e->getMessage();

            // Check if the error message contains error for all sections
            foreach ($sections as $section) {
                $position = mb_strpos($message, $section);
                $this->assertTrue($position !== false);
            }

            return;
        }

        $this->fail('AlgoliaException was not raised');
    }
}
