<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Config;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Test\Integration\Indexing\Config\Traits\ConfigAssertionsTrait;
use Algolia\AlgoliaSearch\Test\Integration\Indexing\MultiStoreTestCase;

/**
 * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
 */
class MultiStoreConfigTest extends MultiStoreTestCase
{
    use ConfigAssertionsTrait;

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
        $fixtureThirdStore = $this->storeRepository->get('fixture_third_store');

        $indicesCreatedByTest = 0;

        $indicesCreatedByTest += $this->countStoreIndices($defaultStore);
        $indicesCreatedByTest += $this->countStoreIndices($fixtureSecondStore);
        $indicesCreatedByTest += $this->countStoreIndices($fixtureThirdStore);

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

        $defaultCategoryIndexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex(
            $this->indexPrefix . 'default_categories',
            $defaultStore->getId()
        );
        $defaultCategoryIndexSettings = $this->algoliaConnector->getSettings($defaultCategoryIndexOptions);

        $fixtureCategoryIndexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex(
            $this->indexPrefix . 'fixture_second_store_categories',
            $fixtureSecondStore->getId()
        );
        $fixtureCategoryIndexSettings = $this->algoliaConnector->getSettings($fixtureCategoryIndexOptions);

        $attributeFromConfig = 'unordered(' . self::ADDITIONAL_ATTRIBUTE . ')';
        $this->assertNotContains($attributeFromConfig, $defaultCategoryIndexSettings['searchableAttributes']);
        $this->assertContains($attributeFromConfig, $fixtureCategoryIndexSettings['searchableAttributes']);

        $rankingFromConfig = 'desc(' . self::ADDITIONAL_ATTRIBUTE . ')';
        $this->assertNotContains($rankingFromConfig, $defaultCategoryIndexSettings['customRanking']);
        $this->assertContains($rankingFromConfig, $fixtureCategoryIndexSettings['customRanking']);

        $defaultIndexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex(
            $this->indexPrefix . 'default_products',
            $defaultStore->getId()
        );
        $defaultProductIndexRules = $this->algoliaConnector->searchRules($defaultIndexOptions);

        $fixtureIndexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex(
            $this->indexPrefix . 'fixture_second_store_products',
            $fixtureSecondStore->getId()
        );
        $fixtureProductIndexRules = $this->algoliaConnector->searchRules($fixtureIndexOptions);

        // Check that the Rule has only been created for the fixture store
        $this->assertEquals(0, $defaultProductIndexRules['nbHits']);
        $this->assertEquals(1, $fixtureProductIndexRules['nbHits']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->setConfig(ConfigHelper::IS_INSTANT_ENABLED, 0);
    }
}
