<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Helper\Entity;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\IndexSettingsHandler;
use Algolia\AlgoliaSearch\Service\Product\FacetBuilder;
use Algolia\AlgoliaSearch\Service\Product\RecordBuilder as ProductRecordBuilder;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock;
use Magento\Eav\Model\Config;
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class ProductHelperTest extends TestCase
{
    private array $defaultSettings = ['searchableAttributes' => [], 'customRanking' => []];
    private int $storeId = 1;

    private null|(Config&MockObject) $eavConfig = null;
    private null|(ConfigHelper&MockObject) $configHelper = null;
    private null|(AlgoliaConnector&MockObject) $algoliaConnector = null;
    private null|(IndexOptionsBuilder&MockObject) $indexOptionsBuilder = null;
    private null|(DiagnosticsLogger&MockObject) $logger = null;
    private null|(StoreManagerInterface&MockObject) $storeManager = null;
    private null|(ManagerInterface&MockObject) $eventManager = null;
    private null|(Visibility&MockObject) $visibility = null;
    private null|(Stock&MockObject) $stockHelper = null;
    private null|(Type&MockObject) $productType = null;
    private null|(CollectionFactory&MockObject) $productCollectionFactory = null;
    private null|(IndexNameFetcher&MockObject) $indexNameFetcher = null;
    private null|(ReplicaManagerInterface&MockObject) $replicaManager = null;
    private null|(ProductInterfaceFactory&MockObject) $productFactory = null;
    private null|(ProductRecordBuilder&MockObject) $productRecordBuilder = null;
    private null|(FacetBuilder&MockObject) $facetBuilder = null;
    private null|(IndexSettingsHandler&MockObject) $indexSettingsHandler = null;
    private null|(ProductHelper&MockObject) $productHelper = null;
    private null|(IndexOptionsInterface&MockObject) $indexOptions = null;
    private null|(IndexOptionsInterface&MockObject) $indexTmpOptions = null;

    protected function setUp(): void
    {
        $this->eavConfig = $this->createMock(Config::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->algoliaConnector = $this->createMock(AlgoliaConnector::class);
        $this->indexOptionsBuilder = $this->createMock(IndexOptionsBuilder::class);
        $this->logger = $this->createMock(DiagnosticsLogger::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->eventManager = $this->createMock(ManagerInterface::class);
        $this->visibility = $this->createMock(Visibility::class);
        $this->stockHelper = $this->createMock(Stock::class);
        $this->productType = $this->createMock(Type::class);
        $this->productCollectionFactory = $this->createMock(CollectionFactory::class);
        $this->indexNameFetcher = $this->createMock(IndexNameFetcher::class);
        $this->replicaManager = $this->createMock(ReplicaManagerInterface::class);
        $this->productFactory = $this->createMock(ProductInterfaceFactory::class);
        $this->productRecordBuilder = $this->createMock(ProductRecordBuilder::class);
        $this->facetBuilder = $this->createMock(FacetBuilder::class);
        $this->indexSettingsHandler = $this->createMock(IndexSettingsHandler::class);

        // Partial mock: stub getIndexSettings and the protected setFacetsQueryRules
        // so setSettings() tests remain isolated from their implementations
        $this->productHelper = $this->getMockBuilder(ProductHelper::class)
            ->setConstructorArgs([
                $this->eavConfig,
                $this->configHelper,
                $this->algoliaConnector,
                $this->indexOptionsBuilder,
                $this->logger,
                $this->storeManager,
                $this->eventManager,
                $this->visibility,
                $this->stockHelper,
                $this->productType,
                $this->productCollectionFactory,
                $this->indexNameFetcher,
                $this->replicaManager,
                $this->productFactory,
                $this->productRecordBuilder,
                $this->facetBuilder,
                $this->indexSettingsHandler,
            ])
            ->onlyMethods(['getIndexSettings', 'setFacetsQueryRules'])
            ->getMock();

        $this->productHelper->method('getIndexSettings')->willReturn($this->defaultSettings);

        $this->indexOptions = $this->createMock(IndexOptionsInterface::class);
        $this->indexOptions->method('getIndexName')->willReturn('prod_index');

        $this->indexTmpOptions = $this->createMock(IndexOptionsInterface::class);
        $this->indexTmpOptions->method('getIndexName')->willReturn('prod_index_tmp');
    }

    public function testSkipsAlgoliaConnectorUpdateWhenSettingsUnchanged(): void
    {
        $this->indexSettingsHandler->method('setSettings')->willReturn(false);

        $this->algoliaConnector->expects($this->never())->method('waitLastTask');
        $this->algoliaConnector->expects($this->never())->method('setSettings');

        $this->productHelper->setSettings($this->indexOptions, $this->indexTmpOptions, $this->storeId);
    }

    public function testWaitsForLastTaskWhenSettingsChanged(): void
    {
        $this->indexSettingsHandler->method('setSettings')->willReturn(true);

        $this->algoliaConnector->expects($this->atLeastOnce())->method('waitLastTask')->with($this->storeId);

        $this->productHelper->setSettings($this->indexOptions, $this->indexTmpOptions, $this->storeId);
    }

    public function testDoesNotPushSettingsToTmpIndexWhenFlagIsFalse(): void
    {
        $this->indexSettingsHandler->method('setSettings')->willReturn(true);

        $this->algoliaConnector->expects($this->never())->method('setSettings');

        $this->productHelper->setSettings($this->indexOptions, $this->indexTmpOptions, $this->storeId, false);
    }

    public function testPushesSettingsToTmpIndexWithMergeParametersWhenFlagIsTrue(): void
    {
        $this->indexSettingsHandler->method('setSettings')->willReturn(true);

        $this->algoliaConnector->expects($this->once())
            ->method('copyIndexConfig')
            ->with(
                $this->indexOptions,
                $this->indexTmpOptions
            );

        $this->productHelper->setSettings($this->indexOptions, $this->indexTmpOptions, $this->storeId, true);
    }

    public function testNoPushSettingsToTmpIndexWithMergeParametersWhenFlagIsFalse(): void
    {
        $this->indexSettingsHandler->method('setSettings')->willReturn(true);

        $this->algoliaConnector->expects($this->never())
            ->method('copyIndexConfig');

        $this->productHelper->setSettings($this->indexOptions, $this->indexTmpOptions, $this->storeId, false);
    }

    public function testAlwaysCallsSetFacetsQueryRulesForMainIndexEvenWhenSettingsUnchanged(): void
    {
        $this->indexSettingsHandler->method('setSettings')->willReturn(false);

        $this->productHelper->expects($this->atLeastOnce())
            ->method('setFacetsQueryRules')
            ->with($this->indexOptions);

        $this->productHelper->setSettings($this->indexOptions, $this->indexTmpOptions, $this->storeId);
    }

    public function testCallsSetFacetsQueryRulesForTmpIndexWhenSaveToTmpIsTrue(): void
    {
        $this->indexSettingsHandler->method('setSettings')->willReturn(false);

        $this->productHelper->expects($this->exactly(2))
            ->method('setFacetsQueryRules')
            ->willReturnCallback(function (IndexOptionsInterface $opts) {
                static $calls = [];
                $calls[] = $opts->getIndexName();
                return null;
            });

        $this->productHelper->setSettings($this->indexOptions, $this->indexTmpOptions, $this->storeId, true);
    }

    public function testDoesNotCallSetFacetsQueryRulesForTmpIndexWhenFlagIsFalse(): void
    {
        $this->indexSettingsHandler->method('setSettings')->willReturn(false);

        $this->productHelper->expects($this->once())
            ->method('setFacetsQueryRules')
            ->with($this->indexOptions);

        $this->productHelper->setSettings($this->indexOptions, $this->indexTmpOptions, $this->storeId, false);
    }

    public function testAlwaysSyncsReplicasToAlgoliaWithIndexSettings(): void
    {
        $this->indexSettingsHandler->method('setSettings')->willReturn(false);

        $this->replicaManager->expects($this->once())
            ->method('syncReplicasToAlgolia')
            ->with($this->storeId, $this->defaultSettings);

        $this->productHelper->setSettings($this->indexOptions, $this->indexTmpOptions, $this->storeId);
    }
}
