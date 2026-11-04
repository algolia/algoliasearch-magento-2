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

        $item = $this->prepareSingleItem($storeManager, ['job_id' => 1, StoreView::STORE_ID => 5]);

        $this->assertSame('Store Five', (string) $item[self::COLUMN_NAME]);
        $this->assertSame(5, $item[StoreView::STORE_ID_NUM]);
    }

    public function testStoreZeroRendersAllStoreViews(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $item = $this->prepareSingleItem($storeManager, ['job_id' => 1, StoreView::STORE_ID => 0]);

        $this->assertSame('All Store Views', (string) $item[self::COLUMN_NAME]);
        $this->assertSame(0, $item[StoreView::STORE_ID_NUM]);
    }

    public function testNullStoreIdRendersEmptyCellInsteadOfAllStoreViews(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        $item = $this->prepareSingleItem($storeManager, ['job_id' => 1, StoreView::STORE_ID => null]);

        $this->assertSame(
            '',
            (string) $item[self::COLUMN_NAME],
            'A NULL store_id is a store-agnostic row and must render empty: the parent compares loosely '
            . 'against store ID 0, so a NULL-first guard is what keeps it from being labeled '
            . '"All Store Views". See MAGE-1734.'
        );
        $this->assertNull($item[StoreView::STORE_ID_NUM]);
    }

    public function testDeletedStoreRendersFallbackLabel(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(7)
            ->willThrowException(new NoSuchEntityException(__('Store does not exist.')));

        $item = $this->prepareSingleItem($storeManager, ['job_id' => 1, StoreView::STORE_ID => 7]);

        $this->assertSame('Store ID: 7 (deleted)', (string) $item[self::COLUMN_NAME]);
        $this->assertSame(7, $item[StoreView::STORE_ID_NUM]);
    }

    /**
     * The rendered item must keep the raw numeric id under StoreView::STORE_ID_NUM
     * The label replaces store_id in the data source, and LandingPageActions / QueryActions
     * rely on that key to scope their frontend URLs.
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
