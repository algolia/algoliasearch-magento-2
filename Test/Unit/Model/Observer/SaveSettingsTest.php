<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Observer;

use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Observer\SaveSettings;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class SaveSettingsTest extends TestCase
{
    private null|(StoreManagerInterface&MockObject) $storeManager = null;
    private null|(IndicesConfigurator&MockObject) $indicesConfigurator = null;
    private null|(Data&MockObject) $helper = null;
    private null|(ProductHelper&MockObject) $productHelper = null;
    private ?SaveSettings $saveSettings = null;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->indicesConfigurator = $this->createMock(IndicesConfigurator::class);
        $this->helper = $this->createMock(Data::class);
        $this->productHelper = $this->createMock(ProductHelper::class);

        $this->saveSettings = new SaveSettings(
            $this->storeManager,
            $this->indicesConfigurator,
            $this->helper,
            $this->productHelper
        );
    }

    private function createObserver(string $eventName): Observer
    {
        $event = $this->createMock(Event::class);
        $event->method('getName')->willReturn($eventName);

        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    private function withStores(array $storeIds, bool $indexingEnabled = true): void
    {
        $stores = [];
        foreach ($storeIds as $id) {
            $stores[$id] = $this->createMock(StoreInterface::class);
        }
        $this->storeManager->method('getStores')->willReturn($stores);
        $this->helper->method('isIndexingEnabled')->willReturn($indexingEnabled);
    }

    public function testCallsSaveConfigurationForEachEnabledStore(): void
    {
        $this->storeManager->method('getStores')->willReturn([
            1 => $this->createMock(StoreInterface::class),
            2 => $this->createMock(StoreInterface::class),
        ]);
        $this->helper->method('isIndexingEnabled')->willReturn(true);

        $this->indicesConfigurator->expects($this->exactly(2))
            ->method('saveConfigurationToAlgolia');

        $this->saveSettings->execute($this->createObserver('some_event'));
    }

    public function testSkipsStoreWhenIndexingIsDisabled(): void
    {
        $this->storeManager->method('getStores')->willReturn([
            1 => $this->createMock(StoreInterface::class),
            2 => $this->createMock(StoreInterface::class),
        ]);
        $this->helper->method('isIndexingEnabled')
            ->willReturnMap([[1, false], [2, true]]);

        $this->indicesConfigurator->expects($this->once())
            ->method('saveConfigurationToAlgolia')
            ->with(2, false, $this->anything());

        $this->saveSettings->execute($this->createObserver('some_event'));
    }

    public function testNeverCallsSaveConfigurationWhenAllStoresHaveIndexingDisabled(): void
    {
        $this->withStores([1, 2], false);

        $this->indicesConfigurator->expects($this->never())
            ->method('saveConfigurationToAlgolia');

        $this->saveSettings->execute($this->createObserver('some_event'));
    }

    /**
     * @dataProvider eventFilteredEntitiesProvider
     */
    public function testForwardsCorrectFilteredEntitiesForEvent(
        string $eventName,
        array $expectedEntities
    ): void {
        $this->withStores([1]);

        $this->indicesConfigurator->expects($this->once())
            ->method('saveConfigurationToAlgolia')
            ->with(1, false, $expectedEntities);

        $this->saveSettings->execute($this->createObserver($eventName));
    }

    public static function eventFilteredEntitiesProvider(): array
    {
        return [
            'instant search section maps to products' => [
                'admin_system_config_changed_section_algoliasearch_instant',
                ['products'],
            ],
            'images section maps to products' => [
                'admin_system_config_changed_section_algoliasearch_images',
                ['products'],
            ],
            'products section maps to products' => [
                'admin_system_config_changed_section_algoliasearch_products',
                ['products'],
            ],
            'categories section maps to categories' => [
                'admin_system_config_changed_section_algoliasearch_categories',
                ['categories'],
            ],
            'unknown event maps to empty filter' => [
                'admin_system_config_changed_section_algoliasearch_unknown',
                [],
            ],
        ];
    }

    public function testAlwaysPassesFalseForUseTmpIndex(): void
    {
        $this->withStores([1]);

        $this->indicesConfigurator->expects($this->once())
            ->method('saveConfigurationToAlgolia')
            ->with(1, false, $this->anything());

        $this->saveSettings->execute($this->createObserver('some_event'));
    }
}
