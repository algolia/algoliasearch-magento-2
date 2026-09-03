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
use PHPUnit\Framework\Attributes\DataProvider;

class SaveSettingsTest extends TestCase
{
    protected function createObjectToTest(
        ?StoreManagerInterface $storeManager = null,
        ?IndicesConfigurator $indicesConfigurator = null,
        ?Data $helper = null,
    ): SaveSettings {
        return new SaveSettings(
            $storeManager ?? $this->createStub(StoreManagerInterface::class),
            $indicesConfigurator ?? $this->createStub(IndicesConfigurator::class),
            $helper ?? $this->createStub(Data::class),
            $this->createStub(ProductHelper::class),
        );
    }

    private function createObserver(string $eventName): Observer
    {
        $event = $this->createStub(Event::class);
        $event->method('getName')->willReturn($eventName);

        $observer = $this->createStub(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    private function createStoreManagerWithStores(array $storeIds): StoreManagerInterface
    {
        $stores = [];
        foreach ($storeIds as $id) {
            $stores[$id] = $this->createStub(StoreInterface::class);
        }

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);

        return $storeManager;
    }

    public function testCallsSaveConfigurationForEachEnabledStore(): void
    {
        $storeManager = $this->createStoreManagerWithStores([1, 2]);

        $helper = $this->createStub(Data::class);
        $helper->method('isIndexingEnabled')->willReturn(true);

        $indicesConfigurator = $this->createMock(IndicesConfigurator::class);
        $indicesConfigurator->expects($this->exactly(2))->method('saveConfigurationToAlgolia');

        $saveSettings = $this->createObjectToTest($storeManager, $indicesConfigurator, $helper);

        $saveSettings->execute($this->createObserver('some_event'));
    }

    public function testSkipsStoreWhenIndexingIsDisabled(): void
    {
        $storeManager = $this->createStoreManagerWithStores([1, 2]);

        $helper = $this->createStub(Data::class);
        $helper->method('isIndexingEnabled')->willReturnMap([[1, false], [2, true]]);

        $indicesConfigurator = $this->createMock(IndicesConfigurator::class);
        $indicesConfigurator->expects($this->once())
            ->method('saveConfigurationToAlgolia')
            ->with(2, false, $this->anything());

        $saveSettings = $this->createObjectToTest($storeManager, $indicesConfigurator, $helper);

        $saveSettings->execute($this->createObserver('some_event'));
    }

    public function testNeverCallsSaveConfigurationWhenAllStoresHaveIndexingDisabled(): void
    {
        $storeManager = $this->createStoreManagerWithStores([1, 2]);

        $helper = $this->createStub(Data::class);
        $helper->method('isIndexingEnabled')->willReturn(false);

        $indicesConfigurator = $this->createMock(IndicesConfigurator::class);
        $indicesConfigurator->expects($this->never())->method('saveConfigurationToAlgolia');

        $saveSettings = $this->createObjectToTest($storeManager, $indicesConfigurator, $helper);

        $saveSettings->execute($this->createObserver('some_event'));
    }

    #[DataProvider('eventFilteredEntitiesProvider')]
    public function testForwardsCorrectFilteredEntitiesForEvent(
        string $eventName,
        array $expectedEntities
    ): void {
        $storeManager = $this->createStoreManagerWithStores([1]);

        $helper = $this->createStub(Data::class);
        $helper->method('isIndexingEnabled')->willReturn(true);

        $indicesConfigurator = $this->createMock(IndicesConfigurator::class);
        $indicesConfigurator->expects($this->once())
            ->method('saveConfigurationToAlgolia')
            ->with(1, false, $expectedEntities);

        $saveSettings = $this->createObjectToTest($storeManager, $indicesConfigurator, $helper);

        $saveSettings->execute($this->createObserver($eventName));
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
        $storeManager = $this->createStoreManagerWithStores([1]);

        $helper = $this->createStub(Data::class);
        $helper->method('isIndexingEnabled')->willReturn(true);

        $indicesConfigurator = $this->createMock(IndicesConfigurator::class);
        $indicesConfigurator->expects($this->once())
            ->method('saveConfigurationToAlgolia')
            ->with(1, false, $this->anything());

        $saveSettings = $this->createObjectToTest($storeManager, $indicesConfigurator, $helper);

        $saveSettings->execute($this->createObserver('some_event'));
    }
}
