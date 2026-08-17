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
use Algolia\AlgoliaSearch\Service\Index\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Index\Settings\IndexSettingsHandler;
use Algolia\AlgoliaSearch\Service\Page\IndexOptionsBuilder as PageIndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder as ProductIndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Suggestion\IndexOptionsBuilder as SuggestionIndexOptionsBuilder;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class IndicesConfiguratorTest extends TestCase
{
    private int $storeId = 1;

    /**
     * Partial mock: stub the protected routing methods so saveConfigurationToAlgolia can be
     * tested in isolation without pulling in each entity's full dependency chain.
     */
    protected function createObjectToTest(
        ?AlgoliaCredentialsManager $algoliaCredentialsManager = null,
        ?Data $baseHelper = null,
        ?DiagnosticsLogger $logger = null,
    ): IndicesConfigurator&MockObject {
        $logger ??= $this->createStub(DiagnosticsLogger::class);
        $logger->method('getStoreName')->willReturn('Default Store');

        return $this->getMockBuilder(IndicesConfigurator::class)
            ->setConstructorArgs([
                $baseHelper ?? $this->createStub(Data::class),
                $this->createStub(IndexOptionsBuilder::class),
                $this->createStub(CategoryIndexOptionsBuilder::class),
                $this->createStub(PageIndexOptionsBuilder::class),
                $this->createStub(ProductIndexOptionsBuilder::class),
                $this->createStub(SuggestionIndexOptionsBuilder::class),
                $this->createStub(AlgoliaConnector::class),
                $this->createStub(ConfigHelper::class),
                $this->createStub(AutocompleteHelper::class),
                $this->createStub(ProductHelper::class),
                $this->createStub(CategoryHelper::class),
                $this->createStub(PageHelper::class),
                $this->createStub(SuggestionHelper::class),
                $this->createStub(AdditionalSectionHelper::class),
                $algoliaCredentialsManager ?? $this->createStub(AlgoliaCredentialsManager::class),
                $this->createStub(IndexSettingsHandler::class),
                $logger,
            ])
            ->onlyMethods(
                [
                    'setAllEntitiesSettings',
                    'setProductsSettings',
                    'setCategoriesSettings',
                    'setPagesSettings',
                    'setQuerySuggestionsSettings',
                    'setExtraSettings'
                ]
            )
            ->getMock();
    }

    private function createPassingGuards(): array
    {
        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentials')->willReturn(true);

        $baseHelper = $this->createStub(Data::class);
        $baseHelper->method('isIndexingEnabled')->willReturn(true);

        return [$algoliaCredentialsManager, $baseHelper];
    }

    public function testReturnsEarlyWhenCredentialCheckFails(): void
    {
        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentials')->willReturn(false);

        $configurator = $this->createObjectToTest(algoliaCredentialsManager: $algoliaCredentialsManager);

        $configurator->expects($this->never())->method('setAllEntitiesSettings');
        $configurator->expects($this->never())->method('setProductsSettings');
        $configurator->expects($this->never())->method('setCategoriesSettings');
        $configurator->expects($this->never())->method('setExtraSettings');

        $configurator->saveConfigurationToAlgolia($this->storeId);
    }

    public function testReturnsEarlyWhenIndexingIsDisabled(): void
    {
        $algoliaCredentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $algoliaCredentialsManager->method('checkCredentials')->willReturn(true);

        $baseHelper = $this->createStub(Data::class);
        $baseHelper->method('isIndexingEnabled')->willReturn(false);

        $configurator = $this->createObjectToTest(
            algoliaCredentialsManager: $algoliaCredentialsManager,
            baseHelper: $baseHelper,
        );

        $configurator->expects($this->never())->method('setAllEntitiesSettings');
        $configurator->expects($this->never())->method('setProductsSettings');
        $configurator->expects($this->never())->method('setCategoriesSettings');
        $configurator->expects($this->never())->method('setExtraSettings');

        $configurator->saveConfigurationToAlgolia($this->storeId);
    }

    public function testCallsAllEntitiesSettingsWhenNoFilterProvided(): void
    {
        [$algoliaCredentialsManager, $baseHelper] = $this->createPassingGuards();
        $configurator = $this->createObjectToTest($algoliaCredentialsManager, $baseHelper);

        $configurator->expects($this->once())->method('setAllEntitiesSettings');
        $configurator->expects($this->never())->method('setProductsSettings');
        $configurator->expects($this->never())->method('setCategoriesSettings');

        $configurator->saveConfigurationToAlgolia($this->storeId);
    }

    public function testForwardsUseTmpIndexToAllEntitiesSettings(): void
    {
        [$algoliaCredentialsManager, $baseHelper] = $this->createPassingGuards();
        $configurator = $this->createObjectToTest($algoliaCredentialsManager, $baseHelper);

        $configurator->expects($this->once())
            ->method('setAllEntitiesSettings')
            ->with($this->storeId, true);

        $configurator->saveConfigurationToAlgolia($this->storeId, true);
    }

    public function testCallsOnlyProductsSettingsWhenFilterContainsProducts(): void
    {
        [$algoliaCredentialsManager, $baseHelper] = $this->createPassingGuards();
        $configurator = $this->createObjectToTest($algoliaCredentialsManager, $baseHelper);

        $configurator->expects($this->once())->method('setProductsSettings');
        $configurator->expects($this->never())->method('setCategoriesSettings');
        $configurator->expects($this->never())->method('setAllEntitiesSettings');

        $configurator->saveConfigurationToAlgolia($this->storeId, false, ['products']);
    }

    public function testForwardsUseTmpIndexToProductsSettings(): void
    {
        [$algoliaCredentialsManager, $baseHelper] = $this->createPassingGuards();
        $configurator = $this->createObjectToTest($algoliaCredentialsManager, $baseHelper);

        $configurator->expects($this->once())
            ->method('setProductsSettings')
            ->with($this->storeId, true);

        $configurator->saveConfigurationToAlgolia($this->storeId, true, ['products']);
    }

    public function testCallsOnlyCategoriesSettingsWhenFilterContainsCategories(): void
    {
        [$algoliaCredentialsManager, $baseHelper] = $this->createPassingGuards();
        $configurator = $this->createObjectToTest($algoliaCredentialsManager, $baseHelper);

        $configurator->expects($this->once())->method('setCategoriesSettings');
        $configurator->expects($this->never())->method('setProductsSettings');
        $configurator->expects($this->never())->method('setAllEntitiesSettings');

        $configurator->saveConfigurationToAlgolia($this->storeId, false, ['categories']);
    }

    public function testCallsBothProductsAndCategoriesWhenBothInFilter(): void
    {
        [$algoliaCredentialsManager, $baseHelper] = $this->createPassingGuards();
        $configurator = $this->createObjectToTest($algoliaCredentialsManager, $baseHelper);

        $configurator->expects($this->once())->method('setProductsSettings');
        $configurator->expects($this->once())->method('setCategoriesSettings');
        $configurator->expects($this->never())->method('setAllEntitiesSettings');

        $configurator->saveConfigurationToAlgolia($this->storeId, false, ['products', 'categories']);
    }

    public function testSkipsUnrecognizedEntitiesInFilterBranch(): void
    {
        [$algoliaCredentialsManager, $baseHelper] = $this->createPassingGuards();
        $configurator = $this->createObjectToTest($algoliaCredentialsManager, $baseHelper);

        $configurator->expects($this->once())->method('setPagesSettings');
        $configurator->expects($this->once())->method('setQuerySuggestionsSettings');
        $configurator->expects($this->never())->method('setProductsSettings');
        $configurator->expects($this->never())->method('setCategoriesSettings');
        $configurator->expects($this->never())->method('setAllEntitiesSettings');

        $configurator->saveConfigurationToAlgolia(
            $this->storeId,
            false,
            ['pages', 'suggestions', 'foo', 'bar']
        );
    }

    public function testForwardsAllParametersToSetExtraSettings(): void
    {
        [$algoliaCredentialsManager, $baseHelper] = $this->createPassingGuards();
        $configurator = $this->createObjectToTest($algoliaCredentialsManager, $baseHelper);

        $filteredEntities = ['products', 'categories'];

        $configurator->expects($this->once())
            ->method('setExtraSettings')
            ->with($this->storeId, true, $filteredEntities);

        $configurator->saveConfigurationToAlgolia($this->storeId, true, $filteredEntities);
    }
}
