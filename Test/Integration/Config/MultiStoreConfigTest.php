<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Config;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Test\Integration\MultiStoreTestCase;

/**
 * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
 */
class MultiStoreConfigTest extends MultiStoreTestCase
{

    const ADDITIONAL_ATTRIBUTE = 'additional_attribute';

    public function testMultiStoreIndicesCreation()
    {
        $websites = $this->storeManager->getWebsites();
        $stores = $this->storeManager->getStores();

        // Check that stores and websites are properly created
        $this->assertEquals(count($websites), 2);
        $this->assertEquals(count($stores), 3);

        foreach ($stores as $store) {
            $this->setupStore($store, true);
        }

        $indicesCreatedByTest = 0;
        $indices = $this->algoliaHelper->listIndexes();

        foreach ($indices['items'] as $index) {
            $name = $index['name'];

            if (mb_strpos($name, $this->indexPrefix) === 0) {
                $indicesCreatedByTest++;
            }
        }

        // Check that the configuration created the appropriate number of indices (7 (4 mains + 3 replicas per store => 3*7=21)
        $this->assertEquals($indicesCreatedByTest, 21);

        $defaultStore = $this->storeRepository->get('default');
        $fixtureSecondStore = $this->storeRepository->get('fixture_second_store');

        // Change category configuration at store level (attributes and ranking)
        $attributesFromConfig = $this->configHelper->getCategoryAdditionalAttributes($defaultStore->getId());
        $attributesFromConfigAlt = $attributesFromConfig;
        $attributesFromConfigAlt[] = [
            "attribute" => self::ADDITIONAL_ATTRIBUTE,
            "searchable" => "1",
            "order" => "unordered",
            "retrievable" => "1",
        ];

        $this->setConfig(
            ConfigHelper::CATEGORY_ATTRIBUTES,
            json_encode($attributesFromConfigAlt),
            $fixtureSecondStore->getCode())
        ;

        $rankingsFromConfig = $this->configHelper->getCategoryCustomRanking($defaultStore->getId());
        $rankingsFromConfigAlt = $rankingsFromConfig;
        $rankingsFromConfigAlt[] = [
            "attribute" => self::ADDITIONAL_ATTRIBUTE,
            "order" => "desc",
        ];

        $this->setConfig(
            ConfigHelper::CATEGORY_CUSTOM_RANKING,
            json_encode($rankingsFromConfigAlt),
            $fixtureSecondStore->getCode())
        ;

        // Query rules check (activate one QR on the fixture store)
        $facetsFromConfig = $this->configHelper->getFacets($defaultStore->getId());
        $facetsFromConfigAlt = $facetsFromConfig;
        foreach ($facetsFromConfigAlt as $key => $facet) {
            if ($facet['attribute'] === "color") {
                $facetsFromConfigAlt[$key]['create_rule'] = "1";
                break;
            }
        }

        $this->setConfig(
            ConfigHelper::FACETS,
            json_encode($facetsFromConfigAlt),
            $fixtureSecondStore->getCode()
        );

        $this->indicesConfigurator->saveConfigurationToAlgolia($fixtureSecondStore->getId());

        $defaultCategoryIndexSettings = $this->algoliaHelper->getSettings($this->indexPrefix . 'default_categories');
        $fixtureCategoryIndexSettings = $this->algoliaHelper->getSettings($this->indexPrefix . 'fixture_second_store_categories');

        $attributeFromConfig = 'unordered(' . self::ADDITIONAL_ATTRIBUTE . ')';
        $this->assertNotContains($attributeFromConfig, $defaultCategoryIndexSettings['searchableAttributes']);
        $this->assertContains($attributeFromConfig, $fixtureCategoryIndexSettings['searchableAttributes']);

        $rankingFromConfig = 'desc(' . self::ADDITIONAL_ATTRIBUTE . ')';
        $this->assertNotContains($rankingFromConfig, $defaultCategoryIndexSettings['customRanking']);
        $this->assertContains($rankingFromConfig, $fixtureCategoryIndexSettings['customRanking']);

        $defaultProductIndexRules = $this->algoliaHelper->searchRules($this->indexPrefix . 'default_products');
        $fixtureProductIndexRules = $this->algoliaHelper->searchRules($this->indexPrefix . 'fixture_second_store_products');

        // Check that the Rule has only been created for the fixture store
        $this->assertEquals($defaultProductIndexRules['nbHits'], 0);
        $this->assertEquals($fixtureProductIndexRules['nbHits'], 1);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->setConfig(ConfigHelper::IS_INSTANT_ENABLED, 0);
    }
}
