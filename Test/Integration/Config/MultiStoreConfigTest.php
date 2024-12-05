<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Config;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
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
        $this->assertEquals(2, count($websites));
        $this->assertEquals(3, count($stores));

        foreach ($stores as $store) {
            $this->setupStore($store, true);
        }

        $defaultStore = $this->storeRepository->get('default');
        $fixtureSecondStore = $this->storeRepository->get('fixture_second_store');

        $indicesCreatedByTest = 0;

        $this->algoliaHelper->setStoreId($defaultStore->getId());
        $indicesCreatedByTest += $this->countStoreIndices();

        $this->algoliaHelper->setStoreId($fixtureSecondStore->getId());
        $indicesCreatedByTest += $this->countStoreIndices();

        // Check that the configuration created the appropriate number of indices (7 (4 mains + 3 replicas per store => 3*7=21)
        $this->assertEquals(21, $indicesCreatedByTest);

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

        $this->algoliaHelper->setStoreId($defaultStore->getId());
        $defaultCategoryIndexSettings = $this->algoliaHelper->getSettings($this->indexPrefix . 'default_categories');

        $this->algoliaHelper->setStoreId($fixtureSecondStore->getId());
        $fixtureCategoryIndexSettings = $this->algoliaHelper->getSettings($this->indexPrefix . 'fixture_second_store_categories');

        $attributeFromConfig = 'unordered(' . self::ADDITIONAL_ATTRIBUTE . ')';
        $this->assertNotContains($attributeFromConfig, $defaultCategoryIndexSettings['searchableAttributes']);
        $this->assertContains($attributeFromConfig, $fixtureCategoryIndexSettings['searchableAttributes']);

        $rankingFromConfig = 'desc(' . self::ADDITIONAL_ATTRIBUTE . ')';
        $this->assertNotContains($rankingFromConfig, $defaultCategoryIndexSettings['customRanking']);
        $this->assertContains($rankingFromConfig, $fixtureCategoryIndexSettings['customRanking']);

        $this->algoliaHelper->setStoreId($defaultStore->getId());
        $defaultProductIndexRules = $this->algoliaHelper->searchRules($this->indexPrefix . 'default_products');

        $this->algoliaHelper->setStoreId($fixtureSecondStore->getId());
        $fixtureProductIndexRules = $this->algoliaHelper->searchRules($this->indexPrefix . 'fixture_second_store_products');

        // Check that the Rule has only been created for the fixture store
        $this->assertEquals(0, $defaultProductIndexRules['nbHits']);
        $this->assertEquals(1, $fixtureProductIndexRules['nbHits']);

        $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);
    }

    /**
     * @return int
     * @throws AlgoliaException
     */
    protected function countStoreIndices(): int
    {
        $indices = $this->algoliaHelper->listIndexes();

        $indicesCreatedByTest = 0;

        foreach ($indices['items'] as $index) {
            $name = $index['name'];

            if (mb_strpos($name, $this->indexPrefix) === 0) {
                $indicesCreatedByTest++;
            }
        }

        return $indicesCreatedByTest;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->setConfig(ConfigHelper::IS_INSTANT_ENABLED, 0);
    }
}
