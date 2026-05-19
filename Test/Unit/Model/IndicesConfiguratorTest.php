<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Model;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\AdditionalSectionHelper;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Category\IndexOptionsBuilder as CategoryIndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\IndexSettingsHandler;
use Algolia\AlgoliaSearch\Service\Page\IndexOptionsBuilder as PageIndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder as ProductIndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Suggestion\IndexOptionsBuilder as SuggestionIndexOptionsBuilder;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class IndicesConfiguratorTest extends TestCase
{
    private int $storeId = 1;

    private null|(Data&MockObject) $baseHelper = null;
    private null|(IndexOptionsBuilder&MockObject) $indexOptionsBuilder = null;
    private null|(CategoryIndexOptionsBuilder&MockObject) $categoryIndexOptionsBuilder = null;
    private null|(PageIndexOptionsBuilder&MockObject) $pageIndexOptionsBuilder = null;
    private null|(ProductIndexOptionsBuilder&MockObject) $productIndexOptionsBuilder = null;
    private null|(SuggestionIndexOptionsBuilder&MockObject) $suggestionIndexOptionsBuilder = null;
    private null|(AlgoliaConnector&MockObject) $algoliaConnector = null;
    private null|(ConfigHelper&MockObject) $configHelper = null;
    private null|(AutocompleteHelper&MockObject) $autocompleteHelper = null;
    private null|(ProductHelper&MockObject) $productHelper = null;
    private null|(CategoryHelper&MockObject) $categoryHelper = null;
    private null|(PageHelper&MockObject) $pageHelper = null;
    private null|(SuggestionHelper&MockObject) $suggestionHelper = null;
    private null|(AdditionalSectionHelper&MockObject) $additionalSectionHelper = null;
    private null|(AlgoliaCredentialsManager&MockObject) $algoliaCredentialsManager = null;
    private null|(IndexSettingsHandler&MockObject) $indexSettingsHandler = null;
    private null|(DiagnosticsLogger&MockObject) $logger = null;
    private null|(IndicesConfigurator&MockObject) $configurator = null;

    protected function setUp(): void
    {
        $this->baseHelper = $this->createMock(Data::class);
        $this->indexOptionsBuilder = $this->createMock(IndexOptionsBuilder::class);
        $this->categoryIndexOptionsBuilder = $this->createMock(CategoryIndexOptionsBuilder::class);
        $this->pageIndexOptionsBuilder = $this->createMock(PageIndexOptionsBuilder::class);
        $this->productIndexOptionsBuilder = $this->createMock(ProductIndexOptionsBuilder::class);
        $this->suggestionIndexOptionsBuilder = $this->createMock(SuggestionIndexOptionsBuilder::class);
        $this->algoliaConnector = $this->createMock(AlgoliaConnector::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->autocompleteHelper = $this->createMock(AutocompleteHelper::class);
        $this->productHelper = $this->createMock(ProductHelper::class);
        $this->categoryHelper = $this->createMock(CategoryHelper::class);
        $this->pageHelper = $this->createMock(PageHelper::class);
        $this->suggestionHelper = $this->createMock(SuggestionHelper::class);
        $this->additionalSectionHelper = $this->createMock(AdditionalSectionHelper::class);
        $this->algoliaCredentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $this->indexSettingsHandler = $this->createMock(IndexSettingsHandler::class);
        $this->logger = $this->createMock(DiagnosticsLogger::class);

        // Partial mock: stub protected routing methods so saveConfigurationToAlgolia
        // can be tested in isolation without pulling in each entity's full dependency chain
        $this->configurator = $this->getMockBuilder(IndicesConfigurator::class)
            ->setConstructorArgs([
                $this->baseHelper,
                $this->indexOptionsBuilder,
                $this->categoryIndexOptionsBuilder,
                $this->pageIndexOptionsBuilder,
                $this->productIndexOptionsBuilder,
                $this->suggestionIndexOptionsBuilder,
                $this->algoliaConnector,
                $this->configHelper,
                $this->autocompleteHelper,
                $this->productHelper,
                $this->categoryHelper,
                $this->pageHelper,
                $this->suggestionHelper,
                $this->additionalSectionHelper,
                $this->algoliaCredentialsManager,
                $this->indexSettingsHandler,
                $this->logger,
            ])
            ->onlyMethods(['setAllEntitiesSettings', 'setProductsSettings', 'setCategoriesSettings', 'setExtraSettings'])
            ->getMock();

        $this->logger->method('getStoreName')->willReturn('Default Store');
    }

    private function allowPassingGuards(): void
    {
        $this->algoliaCredentialsManager->method('checkCredentials')->willReturn(true);
        $this->baseHelper->method('isIndexingEnabled')->willReturn(true);
    }

    public function testReturnsEarlyWhenCredentialCheckFails(): void
    {
        $this->algoliaCredentialsManager->method('checkCredentials')->willReturn(false);

        $this->configurator->expects($this->never())->method('setAllEntitiesSettings');
        $this->configurator->expects($this->never())->method('setProductsSettings');
        $this->configurator->expects($this->never())->method('setCategoriesSettings');
        $this->configurator->expects($this->never())->method('setExtraSettings');

        $this->configurator->saveConfigurationToAlgolia($this->storeId);
    }

    public function testReturnsEarlyWhenIndexingIsDisabled(): void
    {
        $this->algoliaCredentialsManager->method('checkCredentials')->willReturn(true);
        $this->baseHelper->method('isIndexingEnabled')->willReturn(false);

        $this->configurator->expects($this->never())->method('setAllEntitiesSettings');
        $this->configurator->expects($this->never())->method('setProductsSettings');
        $this->configurator->expects($this->never())->method('setCategoriesSettings');
        $this->configurator->expects($this->never())->method('setExtraSettings');

        $this->configurator->saveConfigurationToAlgolia($this->storeId);
    }

    public function testCallsAllEntitiesSettingsWhenNoFilterProvided(): void
    {
        $this->allowPassingGuards();

        $this->configurator->expects($this->once())->method('setAllEntitiesSettings');
        $this->configurator->expects($this->never())->method('setProductsSettings');
        $this->configurator->expects($this->never())->method('setCategoriesSettings');

        $this->configurator->saveConfigurationToAlgolia($this->storeId);
    }

    public function testForwardsUseTmpIndexToAllEntitiesSettings(): void
    {
        $this->allowPassingGuards();

        $this->configurator->expects($this->once())
            ->method('setAllEntitiesSettings')
            ->with($this->storeId, true);

        $this->configurator->saveConfigurationToAlgolia($this->storeId, true);
    }

    public function testCallsOnlyProductsSettingsWhenFilterContainsProducts(): void
    {
        $this->allowPassingGuards();

        $this->configurator->expects($this->once())->method('setProductsSettings');
        $this->configurator->expects($this->never())->method('setCategoriesSettings');
        $this->configurator->expects($this->never())->method('setAllEntitiesSettings');

        $this->configurator->saveConfigurationToAlgolia($this->storeId, false, ['products']);
    }

    public function testForwardsUseTmpIndexToProductsSettings(): void
    {
        $this->allowPassingGuards();

        $this->configurator->expects($this->once())
            ->method('setProductsSettings')
            ->with($this->storeId, true);

        $this->configurator->saveConfigurationToAlgolia($this->storeId, true, ['products']);
    }

    public function testCallsOnlyCategoriesSettingsWhenFilterContainsCategories(): void
    {
        $this->allowPassingGuards();

        $this->configurator->expects($this->once())->method('setCategoriesSettings');
        $this->configurator->expects($this->never())->method('setProductsSettings');
        $this->configurator->expects($this->never())->method('setAllEntitiesSettings');

        $this->configurator->saveConfigurationToAlgolia($this->storeId, false, ['categories']);
    }

    public function testCallsBothProductsAndCategoriesWhenBothInFilter(): void
    {
        $this->allowPassingGuards();

        $this->configurator->expects($this->once())->method('setProductsSettings');
        $this->configurator->expects($this->once())->method('setCategoriesSettings');
        $this->configurator->expects($this->never())->method('setAllEntitiesSettings');

        $this->configurator->saveConfigurationToAlgolia($this->storeId, false, ['products', 'categories']);
    }

    public function testSkipsUnrecognizedEntitiesInFilterBranch(): void
    {
        $this->allowPassingGuards();

        // 'pages' and 'suggestions' have no dedicated dispatch in the filter branch;
        // they are handled exclusively through setExtraSettings
        $this->configurator->expects($this->never())->method('setProductsSettings');
        $this->configurator->expects($this->never())->method('setCategoriesSettings');
        $this->configurator->expects($this->never())->method('setAllEntitiesSettings');

        $this->configurator->saveConfigurationToAlgolia($this->storeId, false, ['pages', 'suggestions']);
    }

    public function testForwardsAllParametersToSetExtraSettings(): void
    {
        $this->allowPassingGuards();

        $filteredEntities = ['products', 'categories'];

        $this->configurator->expects($this->once())
            ->method('setExtraSettings')
            ->with($this->storeId, true, $filteredEntities);

        $this->configurator->saveConfigurationToAlgolia($this->storeId, true, $filteredEntities);
    }
}
