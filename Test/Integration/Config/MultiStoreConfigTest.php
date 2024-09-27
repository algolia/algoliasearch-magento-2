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
            $this->setupStore($store);
        }

        $indicesCreatedByTest = 0;
        $indices = $this->algoliaHelper->listIndexes();

        foreach ($indices['items'] as $index) {
            $name = $index['name'];

            if (mb_strpos($name, $this->indexPrefix) === 0) {
                $indicesCreatedByTest++;
            }
        }

        // Check that the configuration created the appropriate number of indices (4 per store => 3*4=12)
        $this->assertEquals($indicesCreatedByTest, 12);


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

        $this->indicesConfigurator->saveConfigurationToAlgolia($fixtureSecondStore->getId());

        $defaultIndexSettings = $this->algoliaHelper->getSettings($this->indexPrefix . 'default_categories');
        $fixtureIndexSettings = $this->algoliaHelper->getSettings($this->indexPrefix . 'fixture_second_store_categories');

        $attributeFromConfig = 'unordered(' . self::ADDITIONAL_ATTRIBUTE . ')';
        $this->assertNotContains($attributeFromConfig, $defaultIndexSettings['searchableAttributes']);
        $this->assertContains($attributeFromConfig, $fixtureIndexSettings['searchableAttributes']);

        $rankingFromConfig = 'desc(' . self::ADDITIONAL_ATTRIBUTE . ')';
        $this->assertNotContains($rankingFromConfig, $defaultIndexSettings['customRanking']);
        $this->assertContains($rankingFromConfig, $fixtureIndexSettings['customRanking']);
    }
}
