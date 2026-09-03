<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Service\Product\FacetBuilder;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Customer\Api\GroupExcludedWebsiteRepositoryInterface;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class FacetBuilderTest extends TestCase
{
    protected function createObjectToTest(
        ?ConfigHelper $configHelper = null,
        ?InstantSearchHelper $instantSearchHelper = null,
        ?StoreManagerInterface $storeManager = null,
        ?GroupCollection $groupCollection = null,
        ?GroupExcludedWebsiteRepositoryInterface $groupExcludedWebsiteRepository = null,
    ): FacetBuilder {
        return new FacetBuilder(
            $configHelper ?? $this->createStub(ConfigHelper::class),
            $instantSearchHelper ?? $this->createStub(InstantSearchHelper::class),
            $storeManager ?? $this->createStub(StoreManagerInterface::class),
            $groupCollection ?? $this->createStub(GroupCollection::class),
            $groupExcludedWebsiteRepository ?? $this->createStub(GroupExcludedWebsiteRepositoryInterface::class),
        );
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testGetAttributesForFacetingReturnsCorrectFacets(): void
    {
        $storeId = 1;
        $websiteId = 2;

        $instantSearchHelper = $this->createFacetsStub();
        $groupCollection = $this->createGroupsStub();
        [$configHelper, $storeManager] = $this->createStoreConfigStubs($storeId, $websiteId, configHelperAsMock: true);
        $configHelper->method('isCustomerGroupsEnabled')->with($storeId)->willReturn(true);

        $groupExcludedWebsiteRepository = $this->createStub(GroupExcludedWebsiteRepositoryInterface::class);
        $groupExcludedWebsiteRepository->method('getCustomerGroupExcludedWebsites')->willReturn([]);

        $facetBuilder = $this->createObjectToTest($configHelper, $instantSearchHelper, $storeManager, $groupCollection, $groupExcludedWebsiteRepository);

        $result = $facetBuilder->getAttributesForFaceting($storeId);

        $this->assertContains('brand', $result);
        $this->assertContains('searchable(color)', $result);
        $this->assertContains('price.EUR.group_1', $result);
        $this->assertContains('price.USD.group_2', $result);
    }

    public function testGetAttributesForFacetingRespectsGroupPriceSetting(): void
    {
        $storeId = 1;
        $websiteId = 2;

        $instantSearchHelper = $this->createFacetsStub();
        $groupCollection = $this->createGroupsStub();
        [$configHelper, $storeManager] = $this->createStoreConfigStubs($storeId, $websiteId, configHelperAsMock: true);
        $configHelper->method('isCustomerGroupsEnabled')->with($storeId)->willReturn(false);

        $facetBuilder = $this->createObjectToTest($configHelper, $instantSearchHelper, $storeManager, $groupCollection);

        $result = $facetBuilder->getAttributesForFaceting($storeId);

        $this->assertContains('brand', $result);
        $this->assertContains('searchable(color)', $result);
        $this->assertNotContains('price.EUR.group_1', $result);
        $this->assertNotContains('price.USD.group_2', $result);
    }

    public function testGetAttributesForFacetingExcludesWebsites(): void
    {
        $storeId = 1;
        $websiteId = 2;

        $instantSearchHelper = $this->createFacetsStub();
        $groupCollection = $this->createGroupsStub();
        [$configHelper, $storeManager] = $this->createStoreConfigStubs($storeId, $websiteId, configHelperAsMock: true);
        $configHelper->method('isCustomerGroupsEnabled')->with($storeId)->willReturn(true);

        $groupExcludedWebsiteRepository = $this->createStub(GroupExcludedWebsiteRepositoryInterface::class);
        $groupExcludedWebsiteRepository->method('getCustomerGroupExcludedWebsites')->willReturn([$websiteId]);

        $facetBuilder = $this->createObjectToTest($configHelper, $instantSearchHelper, $storeManager, $groupCollection, $groupExcludedWebsiteRepository);

        $result = $facetBuilder->getAttributesForFaceting($storeId);

        $this->assertContains('brand', $result);
        $this->assertContains('searchable(color)', $result);
        $this->assertNotContains('price.EUR.group_1', $result);
        $this->assertNotContains('price.USD.group_2', $result);
    }

    /**
     * attributesForFaceting must include level0 to be selectable via renderingContent/merch rule UI
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testGetAttributesForFacetingIncludesCategoryLevel0(): void
    {
        $storeId = 1;
        $instantSearchHelper = $this->createFacetsStub(asMock: true);
        $instantSearchHelper = $this->withCategoryConfig($instantSearchHelper, $storeId);

        $facetBuilder = $this->createObjectToTest(instantSearchHelper: $instantSearchHelper);

        $result = $facetBuilder->getAttributesForFaceting($storeId);
        $this->assertContains('categories.level0', $result);
    }

    /**
     * If category PLPs are supported then attributesForFaceting must contain category merch meta data
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testGetAttributesForFacetingIncludesMerchMetaData(): void
    {
        $storeId = 1;
        $instantSearchHelper = $this->createFacetsStub(asMock: true);
        $instantSearchHelper = $this->withCategoryConfig($instantSearchHelper, $storeId);

        $facetBuilder = $this->createObjectToTest(instantSearchHelper: $instantSearchHelper);

        $result = $facetBuilder->getAttributesForFaceting($storeId);

        $this->assertContains('categories', $result);
        $this->assertContains('categoryIds', $result);
        $this->assertNotContains('categoryPageId', $result);
    }

    /*
     * When visual merchandising is enabled through Merchandising Studio then a searchable
     * category page ID (default categoryPageId) must be added to attributesForFaceting
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testGetAttributesForFacetingIncludesVisualMerchData(): void
    {
        $storeId = 1;
        $instantSearchHelper = $this->createFacetsStub(asMock: true);
        $instantSearchHelper = $this->withCategoryConfig($instantSearchHelper, $storeId);
        $configHelper = $this->createVisualMerchEnablementStub($storeId);

        $facetBuilder = $this->createObjectToTest($configHelper, $instantSearchHelper);

        $result = $facetBuilder->getAttributesForFaceting($storeId);

        $this->assertContains('searchable(categoryPageId)', $result);
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testGetRenderingContentReturnsExpectedFormat(): void
    {
        $storeId = 1;
        $websiteId = 2;

        $instantSearchHelper = $this->createFacetsStub();
        $groupCollection = $this->createGroupsStub();
        [$configHelper, $storeManager] = $this->createStoreConfigStubs($storeId, $websiteId, configHelperAsMock: true);
        $configHelper->method('isCustomerGroupsEnabled')->with($storeId)->willReturn(true);

        $groupExcludedWebsiteRepository = $this->createStub(GroupExcludedWebsiteRepositoryInterface::class);
        $groupExcludedWebsiteRepository->method('getCustomerGroupExcludedWebsites')->willReturn([]);

        $facetBuilder = $this->createObjectToTest($configHelper, $instantSearchHelper, $storeManager, $groupCollection, $groupExcludedWebsiteRepository);

        $result = $facetBuilder->getRenderingContent($storeId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('facetOrdering', $result);
        $this->assertArrayHasKey('facets', $result['facetOrdering']);
        $this->assertArrayHasKey('order', $result['facetOrdering']['facets']);
        $this->assertArrayHasKey('values', $result['facetOrdering']);
        $this->assertArrayHasKey('brand', $result['facetOrdering']['values']);
        $this->assertArrayHasKey('sortRemainingBy', $result['facetOrdering']['values']['brand']);
        $this->assertContains('brand', $result['facetOrdering']['facets']['order']);
        $this->assertContains('price.EUR.group_1', $result['facetOrdering']['facets']['order']);
        $this->assertContains('price.USD.group_2', $result['facetOrdering']['facets']['order']);
        $this->assertEquals(count($result['facetOrdering']['facets']['order']), count($result['facetOrdering']['values']));
    }

    /**
     * Categories must be added to renderingContent as level0
     * `categories` Object attribute should not be added as it is not compatible for facet render
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testGetRenderingContentFormatsCategoriesAttribute(): void
    {
        $storeId = 1;
        $websiteId = 2;

        $instantSearchHelper = $this->createMock(InstantSearchHelper::class);
        $instantSearchHelper->method('getFacets')->willReturn([
            [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'color'],
            [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'size'],
        ]);
        $instantSearchHelper = $this->withCategoryConfig($instantSearchHelper, $storeId);

        $groupCollection = $this->createGroupsStub();
        [$configHelper, $storeManager] = $this->createStoreConfigStubs($storeId, $websiteId);

        $facetBuilder = $this->createObjectToTest($configHelper, $instantSearchHelper, $storeManager, $groupCollection);

        $result = $facetBuilder->getRenderingContent($storeId);

        $this->assertContains('categories.level0', $result['facetOrdering']['facets']['order']);

        $values = $result['facetOrdering']['values'];
        $this->assertArrayHasKey('categories.level0', $values);
        $this->assertArrayNotHasKey('categories', $values);
    }

    /**
     * Category merch meta data should not be included with renderingContent
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testGetRenderingContentDoesNotIncludeMetaData(): void
    {
        $storeId = 1;
        $instantSearchHelper = $this->createFacetsStub(asMock: true);
        $instantSearchHelper = $this->withCategoryConfig($instantSearchHelper, $storeId);
        $configHelper = $this->createVisualMerchEnablementStub($storeId);

        $facetBuilder = $this->createObjectToTest($configHelper, $instantSearchHelper);

        $result = $facetBuilder->getRenderingContent($storeId);

        $facets = $result['facetOrdering']['facets']['order'];
        $this->assertNotContains('categories', $facets);
        $this->assertNotContains('categoryIds', $facets);
        $this->assertNotContains('categoryPageId', $facets);
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testGetRawFacetsReturnsCorrectStructure(): void
    {
        $storeId = 1;
        $websiteId = 2;

        $instantSearchHelper = $this->createStub(InstantSearchHelper::class);
        $instantSearchHelper->method('getFacets')->willReturn([
            [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'size'],
            [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => FacetBuilder::FACET_ATTRIBUTE_PRICE],
        ]);

        [$configHelper, $storeManager] = $this->createStoreConfigStubs($storeId, $websiteId);

        $facetBuilder = $this->createObjectToTest($configHelper, $instantSearchHelper, $storeManager);

        $result = $this->invokeMethod($facetBuilder, 'getRawFacets', [$storeId]);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals('size', $result[0][FacetBuilder::FACET_KEY_ATTRIBUTE_NAME]);
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testGetPricingAttributesReturnsCorrectValues(): void
    {
        $storeId = 1;
        $websiteId = 2;

        [$configHelper, $storeManager] = $this->createStoreConfigStubs($storeId, $websiteId);

        $facetBuilder = $this->createObjectToTest($configHelper, storeManager: $storeManager);

        $result = $this->invokeMethod($facetBuilder, 'getPricingAttributes', [$storeId]);
        $this->assertContains('price.USD.default', $result);
        $this->assertContains('price.EUR.default', $result);
    }

    public function testDecorateAttributeForFacetingHandlesSearchableCorrectly(): void
    {
        $facetBuilder = $this->createObjectToTest();

        $facet = [
            FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'brand',
            FacetBuilder::FACET_KEY_SEARCHABLE => FacetBuilder::FACET_SEARCHABLE_SEARCHABLE,
        ];
        $result = $this->invokeMethod($facetBuilder, 'decorateAttributeForFaceting', [$facet]);
        $this->assertEquals('searchable(brand)', $result);
    }

    public function testDecorateAttributeForFacetingHandlesFilterOnlyCorrectly(): void
    {
        $facetBuilder = $this->createObjectToTest();

        $facet = [
            FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'size',
            FacetBuilder::FACET_KEY_SEARCHABLE => FacetBuilder::FACET_SEARCHABLE_FILTER_ONLY,
        ];
        $result = $this->invokeMethod($facetBuilder, 'decorateAttributeForFaceting', [$facet]);
        $this->assertEquals('filterOnly(size)', $result);
    }

    private function createFacetsStub(bool $asMock = false): InstantSearchHelper
    {
        $instantSearchHelper = $asMock ? $this->createMock(InstantSearchHelper::class) : $this->createStub(InstantSearchHelper::class);
        $instantSearchHelper->method('getFacets')->willReturn([
            [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'brand', FacetBuilder::FACET_KEY_SEARCHABLE => FacetBuilder::FACET_SEARCHABLE_NOT_SEARCHABLE],
            [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'color', FacetBuilder::FACET_KEY_SEARCHABLE => FacetBuilder::FACET_SEARCHABLE_SEARCHABLE],
            [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => FacetBuilder::FACET_ATTRIBUTE_PRICE],
        ]);

        return $instantSearchHelper;
    }

    /**
     * @return array{0: ConfigHelper, 1: StoreManagerInterface}
     */
    private function createStoreConfigStubs(int $storeId, int $websiteId, bool $configHelperAsMock = false): array
    {
        $configHelper = $configHelperAsMock ? $this->createMock(ConfigHelper::class) : $this->createStub(ConfigHelper::class);
        $configHelper->method('getAllowedCurrencies')->willReturn(['EUR', 'USD']);

        $store = $this->createStub(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn($websiteId);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with($storeId)->willReturn($store);

        return [$configHelper, $storeManager];
    }

    private function withCategoryConfig(InstantSearchHelper $instantSearchHelper, int $storeId): InstantSearchHelper
    {
        $instantSearchHelper->method('shouldReplaceCategories')->with($storeId)->willReturn(true);

        return $instantSearchHelper;
    }

    private function createGroupsStub(): GroupCollection
    {
        $group1 = $this->createMock(Group::class);
        $group1->method('getData')->with('customer_group_id')->willReturn(1);

        $group2 = $this->createMock(Group::class);
        $group2->method('getData')->with('customer_group_id')->willReturn(2);

        $groupCollection = $this->createStub(GroupCollection::class);
        $groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$group1, $group2]));

        return $groupCollection;
    }

    private function createVisualMerchEnablementStub(int $storeId): ConfigHelper
    {
        $configHelper = $this->createMock(ConfigHelper::class);
        $configHelper->method('isVisualMerchEnabled')->with($storeId)->willReturn(true);
        $configHelper->method('getCategoryPageIdAttributeName')->with($storeId)->willReturn('categoryPageId');

        return $configHelper;
    }
}
