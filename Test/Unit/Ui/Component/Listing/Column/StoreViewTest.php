<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Ui\Component\Listing\Column;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\AlgoliaSearch\Ui\Component\Listing\Column\StoreView;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class StoreViewTest extends TestCase
{
    private const string COLUMN_NAME = 'store_id';

    public function testValidStoreRendersStoreName(): void
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getName')->willReturn('Store Five');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(5)->willReturn($store);

        $item = $this->prepareSingleItem($storeManager, ['job_id' => 1, 'store_id' => 5]);

        $this->assertSame('Store Five', (string) $item[self::COLUMN_NAME]);
        $this->assertSame(5, $item['store_id_num']);
    }

    public function testStoreZeroRendersAllStoreViews(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $item = $this->prepareSingleItem($storeManager, ['job_id' => 1, 'store_id' => 0]);

        $this->assertSame('All Store Views', (string) $item[self::COLUMN_NAME]);
        $this->assertSame(0, $item['store_id_num']);
    }

    public function testNullStoreIdRendersEmptyCellInsteadOfAllStoreViews(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $item = $this->prepareSingleItem($storeManager, ['job_id' => 1, 'store_id' => null]);

        $this->assertSame(
            '',
            (string) $item[self::COLUMN_NAME],
            'A NULL store_id is a store-agnostic row and must render empty: the parent compares loosely '
            . 'against store ID 0, so a NULL-first guard is what keeps it from being labeled '
            . '"All Store Views". See MAGE-1734.'
        );
        $this->assertNull($item['store_id_num']);
    }

    public function testDeletedStoreRendersFallbackLabel(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(7)
            ->willThrowException(new NoSuchEntityException(__('Store does not exist.')));

        $item = $this->prepareSingleItem($storeManager, ['job_id' => 1, 'store_id' => 7]);

        $this->assertSame('Store ID: 7 (deleted)', (string) $item[self::COLUMN_NAME]);
        $this->assertSame(7, $item['store_id_num']);
    }

    /**
     * The rendered item must keep the raw numeric id under store_id_num: the label
     * replaces store_id in the data source, and LandingPageActions / QueryActions
     * rely on store_id_num to scope their frontend URLs.
     */
    private function prepareSingleItem(StoreManagerInterface $storeManager, array $item): array
    {
        $column = new StoreView(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $storeManager,
            [],
            ['name' => self::COLUMN_NAME]
        );

        $dataSource = $column->prepareDataSource(['data' => ['items' => [$item]]]);

        return $dataSource['data']['items'][0];
    }
}
