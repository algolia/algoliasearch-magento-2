<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Helper\Entity;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Index\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\Index\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\Index\Settings\IndexSettingsHandler;
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

    /**
     * ProductHelper is partially mocked to isolate setSettings() from getIndexSettings() and
     * setFacetsQueryRules(), whose own real implementations pull in unrelated collaborators.
     * getIndexSettings() is unconditionally called exactly once by setSettings(), so asserting
     * that real invariant here also satisfies PHPUnit's "no expectations" check for this mock.
     */
    protected function createObjectToTest(
        ?ConfigHelper $configHelper = null,
        ?AlgoliaConnector $algoliaConnector = null,
        ?DiagnosticsLogger $logger = null,
        ?IndexSettingsHandler $indexSettingsHandler = null,
        ?ReplicaManagerInterface $replicaManager = null,
        ?FacetBuilder $facetBuilder = null,
    ): ProductHelper&MockObject {
        $productHelper = $this->getMockBuilder(ProductHelper::class)
            ->setConstructorArgs([
                $this->createStub(Config::class),
                $configHelper ?? $this->createStub(ConfigHelper::class),
                $algoliaConnector ?? $this->createStub(AlgoliaConnector::class),
                $this->createStub(IndexOptionsBuilder::class),
                $logger ?? $this->createStub(DiagnosticsLogger::class),
                $this->createStub(StoreManagerInterface::class),
                $this->createStub(ManagerInterface::class),
                $this->createStub(Visibility::class),
                $this->createStub(Stock::class),
                $this->createStub(Type::class),
                $this->createStub(CollectionFactory::class),
                $this->createStub(IndexNameFetcher::class),
                $replicaManager ?? $this->createStub(ReplicaManagerInterface::class),
                $this->createStub(ProductInterfaceFactory::class),
                $this->createStub(ProductRecordBuilder::class),
                $facetBuilder ?? $this->createStub(FacetBuilder::class),
                $indexSettingsHandler ?? $this->createStub(IndexSettingsHandler::class),
            ])
            ->onlyMethods(['getIndexSettings', 'setFacetsQueryRules'])
            ->getMock();

        $productHelper->expects($this->once())->method('getIndexSettings')->willReturn($this->defaultSettings);

        return $productHelper;
    }

    private function createIndexOptions(string $indexName): IndexOptionsInterface
    {
        $indexOptions = $this->createStub(IndexOptionsInterface::class);
        $indexOptions->method('getIndexName')->willReturn($indexName);

        return $indexOptions;
    }

    public function testSkipsAlgoliaConnectorUpdateWhenSettingsUnchanged(): void
    {
        $indexSettingsHandler = $this->createStub(IndexSettingsHandler::class);
        $indexSettingsHandler->method('setSettings')->willReturn(false);

        $algoliaConnector = $this->createMock(AlgoliaConnector::class);
        $algoliaConnector->expects($this->never())->method('waitLastTask');
        $algoliaConnector->expects($this->never())->method('setSettings');

        $productHelper = $this->createObjectToTest(
            indexSettingsHandler: $indexSettingsHandler,
            algoliaConnector: $algoliaConnector,
        );

        $productHelper->setSettings(
            $this->createIndexOptions('prod_index'),
            $this->createIndexOptions('prod_index_tmp'),
            $this->storeId
        );
    }

    public function testDoesNotCollectTaskIdWhenSettingsChanged(): void
    {
        $indexSettingsHandler = $this->createStub(IndexSettingsHandler::class);
        $indexSettingsHandler->method('setSettings')->willReturn(true);

        $indexOptions = $this->createIndexOptions('prod_index');

        $algoliaConnector = $this->createMock(AlgoliaConnector::class);
        $algoliaConnector->expects($this->never())->method('collectTaskIdToWaitFor')->with($indexOptions);

        $productHelper = $this->createObjectToTest(
            indexSettingsHandler: $indexSettingsHandler,
            algoliaConnector: $algoliaConnector,
        );

        $productHelper->setSettings($indexOptions, $this->createIndexOptions('prod_index_tmp'), $this->storeId);
    }

    public function testDoesNotPushSettingsToTmpIndexWhenFlagIsFalse(): void
    {
        $indexSettingsHandler = $this->createStub(IndexSettingsHandler::class);
        $indexSettingsHandler->method('setSettings')->willReturn(true);

        $algoliaConnector = $this->createMock(AlgoliaConnector::class);
        $algoliaConnector->expects($this->never())->method('setSettings');

        $productHelper = $this->createObjectToTest(
            indexSettingsHandler: $indexSettingsHandler,
            algoliaConnector: $algoliaConnector,
        );

        $productHelper->setSettings(
            $this->createIndexOptions('prod_index'),
            $this->createIndexOptions('prod_index_tmp'),
            $this->storeId,
            false
        );
    }

    public function testPushesSettingsToTmpIndexWithMergeParametersWhenFlagIsTrue(): void
    {
        $indexSettingsHandler = $this->createStub(IndexSettingsHandler::class);
        $indexSettingsHandler->method('setSettings')->willReturn(true);

        $indexOptions = $this->createIndexOptions('prod_index');
        $indexTmpOptions = $this->createIndexOptions('prod_index_tmp');

        $algoliaConnector = $this->createMock(AlgoliaConnector::class);
        $algoliaConnector->expects($this->once())
            ->method('copyIndexConfig')
            ->with($indexOptions, $indexTmpOptions);

        $productHelper = $this->createObjectToTest(
            indexSettingsHandler: $indexSettingsHandler,
            algoliaConnector: $algoliaConnector,
        );

        $productHelper->setSettings($indexOptions, $indexTmpOptions, $this->storeId, true);
    }

    public function testNoPushSettingsToTmpIndexWithMergeParametersWhenFlagIsFalse(): void
    {
        $indexSettingsHandler = $this->createStub(IndexSettingsHandler::class);
        $indexSettingsHandler->method('setSettings')->willReturn(true);

        $algoliaConnector = $this->createMock(AlgoliaConnector::class);
        $algoliaConnector->expects($this->never())->method('copyIndexConfig');

        $productHelper = $this->createObjectToTest(
            indexSettingsHandler: $indexSettingsHandler,
            algoliaConnector: $algoliaConnector,
        );

        $productHelper->setSettings(
            $this->createIndexOptions('prod_index'),
            $this->createIndexOptions('prod_index_tmp'),
            $this->storeId,
            false
        );
    }

    public function testAlwaysCallsSetFacetsQueryRulesForMainIndexEvenWhenSettingsUnchanged(): void
    {
        $indexSettingsHandler = $this->createStub(IndexSettingsHandler::class);
        $indexSettingsHandler->method('setSettings')->willReturn(false);

        $indexOptions = $this->createIndexOptions('prod_index');

        $productHelper = $this->createObjectToTest(indexSettingsHandler: $indexSettingsHandler);
        $productHelper->expects($this->atLeastOnce())->method('setFacetsQueryRules')->with($indexOptions);

        $productHelper->setSettings($indexOptions, $this->createIndexOptions('prod_index_tmp'), $this->storeId);
    }

    public function testCallsSetFacetsQueryRulesForTmpIndexWhenSaveToTmpIsTrue(): void
    {
        $indexSettingsHandler = $this->createStub(IndexSettingsHandler::class);
        $indexSettingsHandler->method('setSettings')->willReturn(false);

        $productHelper = $this->createObjectToTest(indexSettingsHandler: $indexSettingsHandler);
        $productHelper->expects($this->exactly(2))
            ->method('setFacetsQueryRules')
            ->willReturnCallback(function (IndexOptionsInterface $opts) {
                static $calls = [];
                $calls[] = $opts->getIndexName();
                return null;
            });

        $productHelper->setSettings(
            $this->createIndexOptions('prod_index'),
            $this->createIndexOptions('prod_index_tmp'),
            $this->storeId,
            true
        );
    }

    public function testDoesNotCallSetFacetsQueryRulesForTmpIndexWhenFlagIsFalse(): void
    {
        $indexSettingsHandler = $this->createStub(IndexSettingsHandler::class);
        $indexSettingsHandler->method('setSettings')->willReturn(false);

        $indexOptions = $this->createIndexOptions('prod_index');

        $productHelper = $this->createObjectToTest(indexSettingsHandler: $indexSettingsHandler);
        $productHelper->expects($this->once())->method('setFacetsQueryRules')->with($indexOptions);

        $productHelper->setSettings($indexOptions, $this->createIndexOptions('prod_index_tmp'), $this->storeId, false);
    }

    public function testAlwaysSyncsReplicasToAlgoliaWithIndexSettings(): void
    {
        $indexSettingsHandler = $this->createStub(IndexSettingsHandler::class);
        $indexSettingsHandler->method('setSettings')->willReturn(false);

        $replicaManager = $this->createMock(ReplicaManagerInterface::class);
        $replicaManager->expects($this->once())
            ->method('syncReplicasToAlgolia')
            ->with($this->storeId, $this->defaultSettings);

        $productHelper = $this->createObjectToTest(
            indexSettingsHandler: $indexSettingsHandler,
            replicaManager: $replicaManager,
        );

        $productHelper->setSettings(
            $this->createIndexOptions('prod_index'),
            $this->createIndexOptions('prod_index_tmp'),
            $this->storeId
        );
    }
}
