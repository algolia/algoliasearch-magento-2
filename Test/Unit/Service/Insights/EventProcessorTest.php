<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Insights;

use Algolia\AlgoliaSearch\Api\Insights\EventProcessorInterface;
use Algolia\AlgoliaSearch\Api\InsightsClient;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Algolia\AlgoliaSearch\Service\Insights\EventProcessor;
use Magento\Catalog\Model\Product;
use Magento\Directory\Model\Currency;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class EventProcessorTest extends TestCase
{
    private ?EventProcessor $eventProcessor = null;
    private ?InsightsClient $insightsClient = null;
    private ?StoreManagerInterface $storeManager = null;
    private ?Store $store = null;
    private ?Currency $currency = null;

    public function setUp(): void
    {
        $this->insightsClient = $this->createMock(InsightsClient::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->store = $this->createMock(Store::class);
        $this->currency = $this->createMock(Currency::class);

        $this->eventProcessor = new EventProcessor();
    }

    // Test dependency validation and setup methods

    public function testConvertedObjectIDsAfterSearchThrowsExceptionWhenClientMissing(): void
    {
        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage("Events model is missing necessary dependencies to function.");

        $this->eventProcessor->convertedObjectIDsAfterSearch(
            'test-event',
            'test-index',
            ['1', '2', '3'],
            'query-123'
        );
    }

    public function testConvertedObjectIDsAfterSearchThrowsExceptionWhenUserTokenMissing(): void
    {
        $this->eventProcessor->setInsightsClient($this->insightsClient);

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage("Events model is missing necessary dependencies to function.");

        $this->eventProcessor->convertedObjectIDsAfterSearch(
            'test-event',
            'test-index',
            ['1', '2', '3'],
            'query-123'
        );
    }

    public function testConvertedObjectIDsAfterSearchThrowsExceptionWhenStoreManagerMissing(): void
    {
        $this->eventProcessor
            ->setInsightsClient($this->insightsClient)
            ->setAnonymousUserToken('user-token');

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage("Events model is missing necessary dependencies to function.");

        $this->eventProcessor->convertedObjectIDsAfterSearch(
            'test-event',
            'test-index',
            ['1', '2', '3'],
            'query-123'
        );
    }

    // Test convertedObjectIDsAfterSearch

    public function testConvertedObjectIDsAfterSearchWithAllDependencies(): void
    {
        $this->setupFullyConfiguredEventProcessor();

        $this->insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $this->assertArrayHasKey('events', $payload);
                    $this->assertCount(1, $payload['events']);

                    $event = $payload['events'][0];
                    $this->assertEquals('conversion', $event['eventType']);
                    $this->assertEquals('test-event', $event['eventName']);
                    $this->assertEquals('test-index', $event['index']);
                    $this->assertEquals('user-token', $event['userToken']);
                    $this->assertEquals(['1', '2', '3'], $event['objectIDs']);
                    $this->assertEquals('query-123', $event['queryID']);

                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $result = $this->eventProcessor->convertedObjectIDsAfterSearch(
            'test-event',
            'test-index',
            ['1', '2', '3'],
            'query-123'
        );
    }

    public function testConvertedObjectIDsAfterSearchWithAuthenticatedToken(): void
    {
        $this->setupFullyConfiguredEventProcessor();
        $this->eventProcessor->setAuthenticatedUserToken('auth-token-123');

        $this->insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $event = $payload['events'][0];
                    $this->assertEquals('auth-token-123', $event['authenticatedUserToken']);
                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $this->eventProcessor->convertedObjectIDsAfterSearch(
            'test-event',
            'test-index',
            ['1'],
            'query-123'
        );
    }

    // Test convertedObjectIDs

    public function testConvertedObjectIDs(): void
    {
        $this->setupFullyConfiguredEventProcessor();

        $this->insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $event = $payload['events'][0];
                    $this->assertEquals('conversion', $event['eventType']);
                    $this->assertEquals('test-event', $event['eventName']);
                    $this->assertEquals('test-index', $event['index']);
                    $this->assertEquals(['1', '2'], $event['objectIDs']);
                    $this->assertArrayNotHasKey('queryID', $event);

                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $this->eventProcessor->convertedObjectIDs(
            'test-event',
            'test-index',
            ['1', '2']
        );
    }

    // Test convertAddToCart

    public function testConvertAddToCart(): void
    {
        $this->setupFullyConfiguredEventProcessor();

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('123');
        $product->method('getPrice')->willReturn(100.0);

        $item = $this->createMock(Item::class);
        $item->method('getProduct')->willReturn($product);
        $item->method('getData')
            ->willReturnMap([
                ['base_price', null, 85.0],
                ['qty_to_add', null, 2]
            ]);
        $item->method('getPrice')->willReturn(85.0);

        $this->insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $event = $payload['events'][0];
                    $this->assertEquals('addToCart', $event['eventSubtype']);
                    $this->assertEquals(['123'], $event['objectIDs']);
                    $this->assertEquals('USD', $event['currency']);
                    $this->assertEquals(170.0, $event['value']); // 85 * 2
                    $this->assertEquals([['price' => 85.0, 'discount' => 15.0, 'quantity' => 2]], $event['objectData']);
                    $this->assertEquals('query-456', $event['queryID']);

                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $this->eventProcessor->convertAddToCart(
            'add-to-cart-event',
            'products-index',
            $item,
            'query-456'
        );
    }

    public function testConvertAddToCartWithoutQueryID(): void
    {
        $this->setupFullyConfiguredEventProcessor();

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('123');
        $product->method('getPrice')->willReturn(100.0);

        $item = $this->createMock(Item::class);
        $item->method('getProduct')->willReturn($product);
        $item->method('getData')
            ->willReturnMap([
                ['base_price', null, 80.0],
                ['qty_to_add', null, 1]
            ]);
        $item->method('getPrice')->willReturn(80.0);

        $this->insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $event = $payload['events'][0];
                    $this->assertArrayNotHasKey('queryID', $event);
                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $this->eventProcessor->convertAddToCart(
            'add-to-cart-event',
            'products-index',
            $item
        );
    }

    // Test convertPurchaseForItems
    public function testConvertPurchaseForItems(): void
    {
        $this->setupFullyConfiguredEventProcessor();

        $items = $this->createOrderItems([
            ['id' => '1', 'price' => 50.0, 'originalPrice' => 60.0, 'cartDiscountAmount' => 10.0, 'qtyOrdered' => 2],
            ['id' => '2', 'price' => 30.0, 'originalPrice' => 35.0, 'cartDiscountAmount' => 5.0, 'qtyOrdered' => 1],
        ]);

        $this->insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $event = $payload['events'][0];
                    $this->assertEquals('purchase', $event['eventSubtype']);
                    $this->assertEquals(['1', '2'], $event['objectIDs']);
                    $this->assertEquals('USD', $event['currency']);
                    $this->assertEquals(115.0, $event['value']); // (50 * 2 - 10) + (30 - 5)
                    $this->assertEquals('query-789', $event['queryID']);

                    $objectData = $event['objectData'];
                    $this->assertCount(2, $objectData);
                    $this->assertEquals(45, $objectData[0]['price']); // 50 - (10/2)
                    $this->assertEquals(15, $objectData[0]['discount']); // 30 - (5/1)
                    $this->assertEquals(2, $objectData[0]['quantity']);

                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $this->eventProcessor->convertPurchaseForItems(
            'purchase-event',
            'products-index',
            $items,
            'query-789'
        );
    }

    public function testConvertPurchaseForItemsEnforcesObjectLimit(): void
    {
        $this->setupFullyConfiguredEventProcessor();

        // Create more items than the limit allows
        $itemsData = [];
        for ($i = 1; $i <= 25; $i++) {
            $itemsData[] = ['id' => (string) $i, 'price' => 10.0, 'originalPrice' => 10.0, 'cartDiscountAmount' => 0.0, 'qtyOrdered' => 1];
        }
        $items = $this->createOrderItems($itemsData);

        $this->insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $event = $payload['events'][0];
                    // Should be limited to MAX_OBJECT_IDS_PER_EVENT (20)
                    $this->assertCount(EventProcessorInterface::MAX_OBJECT_IDS_PER_EVENT, $event['objectIDs']);
                    $this->assertCount(EventProcessorInterface::MAX_OBJECT_IDS_PER_EVENT, $event['objectData']);
                    // But value should include all 25 items
                    $this->assertEquals(250.0, $event['value']); // 25 * 10

                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $this->eventProcessor->convertPurchaseForItems(
            'purchase-event',
            'products-index',
            $items
        );
    }

    // Helper methods

    private function setupFullyConfiguredEventProcessor(): void
    {
        $this->currency->method('getCode')->willReturn('USD');
        $this->store->method('getCurrentCurrency')->willReturn($this->currency);
        $this->storeManager->method('getStore')->willReturn($this->store);

        $this->eventProcessor
            ->setInsightsClient($this->insightsClient)
            ->setAnonymousUserToken('user-token')
            ->setStoreManager($this->storeManager);
    }

    private function createOrderItems(array $itemsData): array
    {
        $items = [];
        foreach ($itemsData as $data) {
            $product = $this->createMock(Product::class);
            $product->method('getId')->willReturn($data['id']);

            $item = $this->createMock(OrderItem::class);
            $item->method('getProduct')->willReturn($product);
            $item->method('getPrice')->willReturn($data['price']);
            $item->method('getOriginalPrice')->willReturn($data['originalPrice']);
            $item->method('getDiscountAmount')->willReturn($data['cartDiscountAmount']);
            $item->method('getQtyOrdered')->willReturn($data['qtyOrdered']);

            $items[] = $item;
        }
        return $items;
    }
}
