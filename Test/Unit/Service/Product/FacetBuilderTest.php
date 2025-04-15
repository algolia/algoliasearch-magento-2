<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Product;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\QuerySuggestions\Facet;
use Algolia\AlgoliaSearch\Service\Product\FacetBuilder;
use Magento\Customer\Api\GroupExcludedWebsiteRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class FacetBuilderTest extends TestCase
{
    protected ?FacetBuilder $facetBuilder;
    protected ?ConfigHelper $configHelper;
    protected ?StoreManagerInterface $storeManager;
    protected ?GroupCollection $groupCollection;
    protected ?GroupExcludedWebsiteRepositoryInterface $groupExcludedWebsiteRepository;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->groupCollection = $this->createMock(GroupCollection::class);
        $this->groupExcludedWebsiteRepository = $this->createMock(GroupExcludedWebsiteRepositoryInterface::class);

        $this->facetBuilder = new FacetBuilderTestable(
            $this->configHelper,
            $this->storeManager,
            $this->groupCollection,
            $this->groupExcludedWebsiteRepository
        );
    }

    protected function mockFacets(): void
    {
        $this->configHelper
            ->method('getFacets')
            ->willReturn([
                [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'brand', FacetBuilder::FACET_KEY_SEARCHABLE => FacetBuilder::FACET_SEARCHABLE_NOT_SEARCHABLE],
                [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'color', FacetBuilder::FACET_KEY_SEARCHABLE => FacetBuilder::FACET_SEARCHABLE_SEARCHABLE],
                [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => FacetBuilder::FACET_ATTRIBUTE_PRICE]
            ]);
    }

    protected function mockStoreConfig($storeId, $websiteId): void
    {
        $this->configHelper
            ->method('getAllowedCurrencies')
            ->willReturn(['EUR', 'USD']);

        $storeMock = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $storeMock->method('getWebsiteId')->willReturn($websiteId);
        $this->storeManager->method('getStore')->with($storeId)->willReturn($storeMock);
    }

    protected function mockCategoryConfig($storeId): void
    {
        $this->configHelper
            ->method('replaceCategories')
            ->with($storeId)
            ->willReturn(true);
    }

    protected function mockGroups(): void
    {
        $groupMock1 = $this->createMock(\Magento\Customer\Model\Group::class);
        $groupMock1->method('getData')->with('customer_group_id')->willReturn(1);

        $groupMock2 = $this->createMock(\Magento\Customer\Model\Group::class);
        $groupMock2->method('getData')->with('customer_group_id')->willReturn(2);

        $this->groupCollection->method('getIterator')->willReturn(new \ArrayIterator([$groupMock1, $groupMock2]));
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testGetAttributesForFacetingReturnsCorrectFacets(): void
    {
        $storeId = 1;
        $websiteId = 2;

        $this->mockFacets();
        $this->mockGroups();

        $this->mockStoreConfig($storeId, $websiteId);

        $this->configHelper
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        $this->groupExcludedWebsiteRepository
            ->method('getCustomerGroupExcludedWebsites')
            ->willReturn([]);

        $result = $this->facetBuilder->getAttributesForFaceting($storeId);

        $this->assertContains('brand', $result);
        $this->assertContains('searchable(color)', $result);
        $this->assertContains('price.EUR.group_1', $result);
        $this->assertContains('price.USD.group_2', $result);
    }

    public function testGetAttributesForFacetingRespectsGroupPriceSetting(): void
    {
        $storeId = 1;
        $websiteId = 2;

        $this->mockFacets();
        $this->mockGroups();

        $this->mockStoreConfig($storeId, $websiteId);

        $this->configHelper
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(false);

        $result = $this->facetBuilder->getAttributesForFaceting($storeId);

        $this->assertContains('brand', $result);
        $this->assertContains('searchable(color)', $result);
        $this->assertNotContains('price.EUR.group_1', $result);
        $this->assertNotContains('price.USD.group_2', $result);
    }

    public function testGetAttributesForFacetingExcludesWebsites(): void
    {
        $storeId = 1;
        $websiteId = 2;

        $this->mockFacets();
        $this->mockGroups();

        $this->mockStoreConfig($storeId, $websiteId);

        $this->configHelper
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        $this->groupExcludedWebsiteRepository
            ->method('getCustomerGroupExcludedWebsites')
            ->willReturn([$websiteId]);

        $result = $this->facetBuilder->getAttributesForFaceting($storeId);

        $this->assertContains('brand', $result);
        $this->assertContains('searchable(color)', $result);
        $this->assertNotContains('price.EUR.group_1', $result);
        $this->assertNotContains('price.USD.group_2', $result);
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testGetRenderingContentReturnsExpectedFormat(): void
    {
        $storeId = 1;
        $websiteId = 2;
        $this->mockFacets();
        $this->mockGroups();
        $this->mockStoreConfig($storeId, $websiteId);

        $this->configHelper
            ->method('isCustomerGroupsEnabled')
            ->with($storeId)
            ->willReturn(true);

        $this->groupExcludedWebsiteRepository
            ->method('getCustomerGroupExcludedWebsites')
            ->willReturn([]);

        $result = $this->facetBuilder->getRenderingContent($storeId);

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
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testGetRenderingContentFormatsCategoriesAttribute(): void
    {
        $storeId = 1;
        $websiteId = 2;
        $this->configHelper
            ->method('getFacets')
            ->willReturn([
                [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'color'],
                [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'size']
            ]);

        $this->mockGroups();
        $this->mockStoreConfig($storeId, $websiteId);
        $this->mockCategoryConfig($storeId);

        $result = $this->facetBuilder->getRenderingContent($storeId);

        $this->assertContains('categories.level0', $result['facetOrdering']['facets']['order']);

        $values = $result['facetOrdering']['values'];
        $this->assertArrayHasKey('categories.level0', $values);
        $this->assertArrayNotHasKey('categories', $values);
    }

    /**
     * attributesForFaceting must include level0 to be selectable via renderingContent/merch rule UI
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testGetAttributesForFacetingIncludesCategoryLevel0(): void
    {
        $storeId = 1;
        $this->mockFacets();
        $this->mockCategoryConfig($storeId);
        $result = $this->facetBuilder->getAttributesForFaceting($storeId);
        $this->assertContains('categories.level0', $result);
    }

    /**
     * If category PLPs are supported then attributesForFaceting must contain category merch meta data
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testGetAttributesForFacetingIncludesMerchMetaData(): void
    {
        $storeId = 1;
        $this->mockFacets();
        $this->mockCategoryConfig($storeId);

        $result = $this->facetBuilder->getAttributesForFaceting($storeId);

        $this->assertContains('categories', $result);
        $this->assertContains('categoryIds', $result);
        $this->assertNotContains('categoryPageId', $result);
    }

    /*
     * When visual merchandising is enabled through Merchandising Studio then a searchable
     * category page ID (default categoryPageId) must be added to attributesForFaceting
     *  
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testGetAttributesForFacetingIncludesVisualMerchData(): void
    {
        $storeId = 1;
        $this->mockFacets();
        $this->mockCategoryConfig($storeId);
        $this->configHelper
            ->method('isVisualMerchEnabled')
            ->with($storeId)
            ->willReturn(true);
        $this->configHelper
            ->method('getCategoryPageIdAttributeName')
            ->with($storeId)
            ->willReturn('categoryPageId');

        $result = $this->facetBuilder->getAttributesForFaceting($storeId);

        $this->assertContains('searchable(categoryPageId)', $result);
    }

    public function testGetRenderingContentDoesNotIncludeMetaData(): void
    {
        $storeId = 1;
        $this->mockFacets();
        $this->mockCategoryConfig($storeId);

        $result = $this->facetBuilder->getRenderingContent($storeId);

        $facets = $result['facetOrdering']['facets']['order'];
        $this->assertNotContains('categories', $facets);
        $this->assertNotContains('categoryIds', $facets);
        $this->assertNotContains('categoryPageId', $facets);
    }


    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testGetRawFacetsReturnsCorrectStructure(): void
    {
        $storeId = 1;
        $websiteId = 2;

        $this->configHelper
            ->method('getFacets')
            ->willReturn([
                [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'size'],
                [FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => FacetBuilder::FACET_ATTRIBUTE_PRICE]
            ]);

        $this->mockStoreConfig($storeId, $websiteId);

        $result = $this->facetBuilder->getRawFacets($storeId);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals('size', $result[0][FacetBuilder::FACET_KEY_ATTRIBUTE_NAME]);
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testGetPricingAttributesReturnsCorrectValues(): void
    {
        $storeId = 1;
        $websiteId = 2;

        $this->mockStoreConfig($storeId, $websiteId);

        $result = $this->facetBuilder->getPricingAttributes($storeId);
        $this->assertContains('price.USD.default', $result);
        $this->assertContains('price.EUR.default', $result);
    }

    public function testDecorateAttributeForFacetingHandlesSearchableCorrectly(): void
    {
        $facet = [
            FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'brand',
            FacetBuilder::FACET_KEY_SEARCHABLE => FacetBuilder::FACET_SEARCHABLE_SEARCHABLE
        ];
        $result = $this->facetBuilder->decorateAttributeForFaceting($facet);
        $this->assertEquals('searchable(brand)', $result);
    }

    public function testDecorateAttributeForFacetingHandlesFilterOnlyCorrectly(): void
    {
        $facet = [
            FacetBuilder::FACET_KEY_ATTRIBUTE_NAME => 'size',
            FacetBuilder::FACET_KEY_SEARCHABLE => FacetBuilder::FACET_SEARCHABLE_FILTER_ONLY
        ];
        $result = $this->facetBuilder->decorateAttributeForFaceting($facet);
        $this->assertEquals('filterOnly(size)', $result);
    }
}
