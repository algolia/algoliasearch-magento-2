<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Insights;

use Algolia\AlgoliaSearch\Api\Insights\EventProcessorInterface;
use Algolia\AlgoliaSearch\Api\InsightsClient;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Algolia\AlgoliaSearch\Service\Insights\EventProcessor;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Catalog\Model\Product;
use Magento\Directory\Model\Currency;
use Magento\Framework\Locale\FormatInterface as LocaleFormatInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Config as TaxConfig;
use PHPUnit\Framework\Attributes\DataProvider;

class EventProcessorTest extends TestCase
{
    protected function createObjectToTest(
        ?TaxConfig $taxConfig = null,
        ?StoreManagerInterface $storeManager = null,
        ?LocaleFormatInterface $localeFormat = null,
    ): EventProcessor {
        return new EventProcessor(
            $taxConfig ?? $this->createStub(TaxConfig::class),
            $storeManager ?? $this->createStub(StoreManagerInterface::class),
            $localeFormat ?? $this->createStub(LocaleFormatInterface::class),
        );
    }

    private function createFullyConfiguredEventProcessor(
        ?InsightsClient $insightsClient = null,
        ?TaxConfig $taxConfig = null,
        int $decimalPrecision = PriceCurrencyInterface::DEFAULT_PRECISION,
    ): EventProcessor {
        $currency = $this->createStub(Currency::class);
        $currency->method('getCode')->willReturn('USD');

        $store = $this->createStub(Store::class);
        $store->method('getCurrentCurrency')->willReturn($currency);
        $store->method('getId')->willReturn(1);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $localeFormat = $this->createStub(LocaleFormatInterface::class);
        $localeFormat->method('getPriceFormat')->willReturn(['requiredPrecision' => $decimalPrecision]);

        $eventProcessor = $this->createObjectToTest($taxConfig, $storeManager, $localeFormat);

        $eventProcessor
            ->setInsightsClient($insightsClient ?? $this->createStub(InsightsClient::class))
            ->setAnonymousUserToken('user-token');

        return $eventProcessor;
    }

    // Test dependency validation and setup methods

    public function testConvertedObjectIDsAfterSearchThrowsExceptionWhenMissingDependencies(): void
    {
        $eventProcessor = $this->createObjectToTest();

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Events model is missing necessary dependencies to function.');

        $eventProcessor->convertedObjectIDsAfterSearch(
            'test-event',
            'test-index',
            ['1', '2', '3'],
            'query-123'
        );
    }

    public function testConvertedObjectIDsAfterSearchThrowsExceptionWhenUserTokenMissing(): void
    {
        $eventProcessor = $this->createObjectToTest();
        $eventProcessor->setInsightsClient($this->createStub(InsightsClient::class));

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Events model is missing necessary dependencies to function.');

        $eventProcessor->convertedObjectIDsAfterSearch(
            'test-event',
            'test-index',
            ['1', '2', '3'],
            'query-123'
        );
    }

    public function testConvertedObjectIDsAfterSearchThrowsExceptionWhenInsightsClientMissing(): void
    {
        $eventProcessor = $this->createObjectToTest();
        $eventProcessor->setAnonymousUserToken('user-token');

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Events model is missing necessary dependencies to function.');

        $eventProcessor->convertedObjectIDsAfterSearch(
            'test-event',
            'test-index',
            ['1', '2', '3'],
            'query-123'
        );
    }

    // Test convertedObjectIDsAfterSearch

    public function testConvertedObjectIDsAfterSearchWithAllDependencies(): void
    {
        $insightsClient = $this->createMock(InsightsClient::class);
        $insightsClient
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

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient);

        $eventProcessor->convertedObjectIDsAfterSearch(
            'test-event',
            'test-index',
            ['1', '2', '3'],
            'query-123'
        );
    }

    public function testConvertedObjectIDsAfterSearchWithAuthenticatedToken(): void
    {
        $insightsClient = $this->createMock(InsightsClient::class);
        $insightsClient
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

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient);
        $eventProcessor->setAuthenticatedUserToken('auth-token-123');

        $eventProcessor->convertedObjectIDsAfterSearch(
            'test-event',
            'test-index',
            ['1'],
            'query-123'
        );
    }

    // Test convertedObjectIDs

    public function testConvertedObjectIDs(): void
    {
        $insightsClient = $this->createMock(InsightsClient::class);
        $insightsClient
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

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient);

        $eventProcessor->convertedObjectIDs(
            'test-event',
            'test-index',
            ['1', '2']
        );
    }

    // Test convertAddToCart

    public function testConvertAddToCart(): void
    {
        $product = $this->createProductStub('123', 100.0);
        $item = $this->createItemStub($product, 85.0, 2);

        $insightsClient = $this->createMock(InsightsClient::class);
        $insightsClient
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

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient);

        $eventProcessor->convertAddToCart(
            'add-to-cart-event',
            'products-index',
            $item,
            'query-456'
        );
    }

    public function testConvertAddToCartWithoutQueryID(): void
    {
        $product = $this->createProductStub('123', 100.0);
        $item = $this->createItemStub($product, 80.0, 1);

        $insightsClient = $this->createMock(InsightsClient::class);
        $insightsClient
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

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient);

        $eventProcessor->convertAddToCart(
            'add-to-cart-event',
            'products-index',
            $item
        );
    }

    public function testConvertAddToCartFloatingPointPrecision(): void
    {
        $product = $this->createProductStub('123', 23.99);
        $item = $this->createItemStub($product, 23.93, 1);

        $insightsClient = $this->createMock(InsightsClient::class);
        $insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $event = $payload['events'][0];
                    $this->assertEquals([['price' => 23.93, 'discount' => .06, 'quantity' => 1]], $event['objectData']);

                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient);

        $eventProcessor->convertAddToCart(
            'add-to-cart-event',
            'products-index',
            $item
        );
    }

    public function testConvertAddToCartWithStandardDecimalPrecision(): void
    {
        $product = $this->createProductStub('123', 23.992);
        $item = $this->createItemStub($product, 23.931, 1);

        $insightsClient = $this->createMock(InsightsClient::class);
        $insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $event = $payload['events'][0];
                    $this->assertEquals([['price' => 23.93, 'discount' => .06, 'quantity' => 1]], $event['objectData']);

                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient, decimalPrecision: 2);

        $eventProcessor->convertAddToCart(
            'add-to-cart-event',
            'products-index',
            $item
        );
    }

    public function testConvertAddToCartWith3PointDecimalPrecision(): void
    {
        $product = $this->createProductStub('123', 23.992);
        $item = $this->createItemStub($product, 23.931, 1);

        $insightsClient = $this->createMock(InsightsClient::class);
        $insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $event = $payload['events'][0];
                    $this->assertEquals([['price' => 23.931, 'discount' => .061, 'quantity' => 1]], $event['objectData']);

                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient, decimalPrecision: 3);

        $eventProcessor->convertAddToCart(
            'add-to-cart-event',
            'products-index',
            $item
        );
    }

    // Test convertPurchaseForItems
    public function testConvertPurchaseForItems(): void
    {
        $items = $this->createOrderItems([
            ['id' => '1', 'price' => 50.0, 'originalPrice' => 60.0, 'cartDiscountAmount' => 10.0, 'qtyOrdered' => 2],
            ['id' => '2', 'price' => 30.0, 'originalPrice' => 35.0, 'cartDiscountAmount' => 5.0, 'qtyOrdered' => 1],
        ]);

        $insightsClient = $this->createMock(InsightsClient::class);
        $insightsClient
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

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient);

        $eventProcessor->convertPurchaseForItems(
            'purchase-event',
            'products-index',
            $items,
            'query-789'
        );
    }

    public function testConvertPurchaseForItemsEnforcesObjectLimit(): void
    {
        // Create more items than the limit allows
        $itemsData = [];
        for ($i = 1; $i <= 25; $i++) {
            $itemsData[] = ['id' => (string) $i, 'price' => 10.0, 'originalPrice' => 10.0, 'cartDiscountAmount' => 0.0, 'qtyOrdered' => 1];
        }
        $items = $this->createOrderItems($itemsData);

        $insightsClient = $this->createMock(InsightsClient::class);
        // Should be limited to MAX_OBJECT_IDS_PER_EVENT (20)
        $insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $event = $payload['events'][0];
                    $this->assertCount(EventProcessorInterface::MAX_OBJECT_IDS_PER_EVENT, $event['objectIDs']);
                    $this->assertCount(EventProcessorInterface::MAX_OBJECT_IDS_PER_EVENT, $event['objectData']);
                    // But value should include all 25 items
                    $this->assertEquals(250.0, $event['value']); // 25 * 10

                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient);

        $eventProcessor->convertPurchaseForItems(
            'purchase-event',
            'products-index',
            $items
        );
    }

    public function testConvertPurchaseForItemsFloatingPointPrecision(): void
    {
        // These additions should trigger floating point precision errors if rounding is not applied
        $items = $this->createOrderItems([
            ['id' => '1', 'price' => 10.10, 'originalPrice' => 15.00, 'cartDiscountAmount' => 0, 'qtyOrdered' => 1],
            ['id' => '2', 'price' => 33.20, 'originalPrice' => 35.00, 'cartDiscountAmount' => 0, 'qtyOrdered' => 1],
        ]);

        $insightsClient = $this->createMock(InsightsClient::class);
        $insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $event = $payload['events'][0];
                    $this->assertEquals(43.30, $event['value']); // 10.10 + 33.20

                    $objectData = $event['objectData'];
                    $this->assertEquals(['price' => 10.10, 'discount' => 4.90, 'quantity' => 1], $objectData[0]); // 15.00 - 10.10
                    $this->assertEquals(['price' => 33.20, 'discount' => 1.80, 'quantity' => 1], $objectData[1]); // 35.00 - 33.20

                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient);

        $eventProcessor->convertPurchaseForItems(
            'purchase-event',
            'products-index',
            $items,
            'query-123'
        );
    }

    /**
     * The way discounts are recorded in Algolia are per product but Magento is per line item
     * This test ensures the expected values are returned and that binary math does not impact the final value
     */
    public function testConvertPurchaseForItemsFloatingPointPrecisionWithCartDiscount(): void
    {
        // These additions should trigger floating point precision errors if rounding is not applied
        $items = $this->createOrderItems([
            ['id' => '1', 'price' => 10.00, 'originalPrice' => 10.00, 'cartDiscountAmount' => .30, 'qtyOrdered' => 3],
        ]);

        $insightsClient = $this->createMock(InsightsClient::class);
        $insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $event = $payload['events'][0];
                    $this->assertEquals(29.70, $event['value']); // 10.00 * 3

                    $objectData = $event['objectData'];
                    $this->assertEquals(['price' => 9.90, 'discount' => .10, 'quantity' => 3], $objectData[0]);

                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient);

        $eventProcessor->convertPurchaseForItems(
            'purchase-event',
            'products-index',
            $items,
            'query-123'
        );
    }

    // Test convertPurchase

    public function testConvertPurchaseGroupsByQueryID(): void
    {
        $order = $this->createStub(Order::class);

        $items = [
            $this->createOrderItemWithQueryId('1', 'query-1', 50.0, 1),
            $this->createOrderItemWithQueryId('2', 'query-1', 30.0, 1),
            $this->createOrderItemWithQueryId('3', 'query-2', 25.0, 2),
            $this->createOrderItemWithQueryId('4', null, 15.0, 1), // No query ID
        ];

        $order->method('getAllVisibleItems')->willReturn($items);

        $insightsClient = $this->createMock(InsightsClient::class);
        $insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    // There should be 3 different events for each query ID scenario: query-1, query-2, and no-query
                    $events = $payload['events'];
                    $this->assertCount(3, $events);
                    $this->assertEquals(80.0, $events[0]['value']); // 50 + 30
                    $this->assertEquals(50.0, $events[1]['value']); // 25 * 2
                    $this->assertEquals(15.0, $events[2]['value']); // missing query ID should be final event

                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient);

        $result = $eventProcessor->convertPurchase(
            'purchase-event',
            'products-index',
            $order
        );
        // 3 events in one batch
        $this->assertCount(1, $result);
    }

    public function testConvertPurchaseHandlesLargeOrders(): void
    {
        $order = $this->createStub(Order::class);

        // Create more events than MAX_EVENTS_PER_REQUEST allows
        $items = [];
        for ($i = 1; $i <= 1500; $i++) {
            $items[] = $this->createOrderItemWithQueryId((string) $i, "query-$i", 10.0, 1);
        }

        $order->method('getAllVisibleItems')->willReturn($items);

        $insightsClient = $this->createMock(InsightsClient::class);
        // Should be called twice due to chunking (1000 + 500)
        $insightsClient
            ->expects($this->exactly(2))
            ->method('pushEvents')
            ->willReturn(['status' => 'ok']);

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient);

        $result = $eventProcessor->convertPurchase(
            'purchase-event',
            'products-index',
            $order
        );

        $this->assertCount(2, $result); // 2 chunks
    }

    /**
     * Insights API requires that `objectIDs` be submitted as strings
     */
    public function testConvertPurchaseUsesStringIds(): void
    {
        // These additions should trigger floating point precision errors if rounding is not applied
        $items = $this->createOrderItems([
            ['id' => 10, 'price' => 10.00, 'originalPrice' => 10.00, 'cartDiscountAmount' => 0, 'qtyOrdered' => 1],
            ['id' => '20', 'price' => 20.00, 'originalPrice' => 20.00, 'cartDiscountAmount' => 0, 'qtyOrdered' => 1],
            ['id' => 30.0, 'price' => 30.00, 'originalPrice' => 30.00, 'cartDiscountAmount' => 0, 'qtyOrdered' => 1],
        ]);

        $insightsClient = $this->createMock(InsightsClient::class);
        $insightsClient
            ->expects($this->once())
            ->method('pushEvents')
            ->with(
                $this->callback(function ($payload) {
                    $event = $payload['events'][0];
                    $objectIds = $event['objectIDs'];
                    foreach ($objectIds as $objectId) {
                        $this->assertIsString($objectId);
                    }

                    return true;
                }),
                []
            )
            ->willReturn(['status' => 'ok']);

        $eventProcessor = $this->createFullyConfiguredEventProcessor($insightsClient);

        $eventProcessor->convertPurchaseForItems(
            'purchase-event',
            'products-index',
            $items,
            'query-123'
        );
    }

    // Test protected methods

    #[DataProvider('orderItemsProvider')]
    public function testObjectDataForPurchase($priceIncludesTax, $orderItemsData, $expectedResult, $expectedTotalRevenue): void
    {
        $taxConfig = $this->createStub(TaxConfig::class);
        $taxConfig->method('priceIncludesTax')->willReturn($priceIncludesTax);

        $eventProcessor = $this->createFullyConfiguredEventProcessor(taxConfig: $taxConfig);

        $orderItems = [];

        foreach ($orderItemsData as $orderItemData) {
            $orderItem = $this->createStub(OrderItem::class);

            foreach ($orderItemData as $method => $value) {
                $orderItem->method($method)->willReturn($value);
            }

            $orderItems[] = $orderItem;
        }

        $object = $this->invokeMethod($eventProcessor, 'getObjectDataForPurchase', [$orderItems]);
        $this->assertEquals($expectedResult, $object);

        $totalRevenue = $this->invokeMethod($eventProcessor, 'getTotalRevenueForEvent', [$object]);
        $this->assertEquals($expectedTotalRevenue, $totalRevenue);
    }

    // Data providers

    public static function orderItemsProvider(): array
    {
        return [
            [ // One item
                'priceIncludesTax' => true,
                'orderItemsData' => [
                    [
                        'getPrice' => 32.00,
                        'getPriceInclTax' => 32.00,
                        'getOriginalPrice' => 32.00,
                        'getDiscountAmount' => 0.00,
                        'getQtyOrdered' => 1,
                    ],
                ],
                'expectedResult' => [
                    [
                        'price' => 32.00,
                        'discount' => 0.00,
                        'quantity' => 1,
                    ],
                ],
                'expectedTotalRevenue' => 32.00,
            ],
            [ // One item (tax excluded)
                'priceIncludesTax' => false,
                'orderItemsData' => [
                    [
                        'getPrice' => 25.00,
                        'getPriceInclTax' => 32.00,
                        'getOriginalPrice' => 25.00,
                        'getDiscountAmount' => 0.00,
                        'getQtyOrdered' => 1,
                    ],
                ],
                'expectedResult' => [
                    [
                        'price' => 25.00,
                        'discount' => 0.00,
                        'quantity' => 1,
                    ],
                ],
                'expectedTotalRevenue' => 25.00,
            ],
            [ // One item with discount
                'priceIncludesTax' => true,
                'orderItemsData' => [
                    [
                        'getPrice' => 32.00,
                        'getPriceInclTax' => 32.00,
                        'getOriginalPrice' => 32.00,
                        'getDiscountAmount' => 7.00,
                        'getQtyOrdered' => 1,
                    ],
                ],
                'expectedResult' => [
                    [
                        'price' => 25.00,
                        'discount' => 7.00,
                        'quantity' => 1,
                    ],
                ],
                'expectedTotalRevenue' => 25.00,
            ],
            [ // One item with discount (tax excluded)
                'priceIncludesTax' => false,
                'orderItemsData' => [
                    [
                        'getPrice' => 25.00,
                        'getPriceInclTax' => 32.00,
                        'getOriginalPrice' => 25.00,
                        'getDiscountAmount' => 7.00,
                        'getQtyOrdered' => 1,
                    ],
                ],
                'expectedResult' => [
                    [
                        'price' => 18.00,
                        'discount' => 7.00,
                        'quantity' => 1,
                    ],
                ],
                'expectedTotalRevenue' => 18.00,
            ],
            [ // Two items
                'priceIncludesTax' => true,
                'orderItemsData' => [
                    [
                        'getPrice' => 32.00,
                        'getPriceInclTax' => 32.00,
                        'getOriginalPrice' => 32.00,
                        'getDiscountAmount' => 7.00,
                        'getQtyOrdered' => 1,
                    ],
                    [
                        'getPrice' => 32.00,
                        'getPriceInclTax' => 32.00,
                        'getOriginalPrice' => 32.00,
                        'getDiscountAmount' => 0.00,
                        'getQtyOrdered' => 2,
                    ],
                ],
                'expectedResult' => [
                    [
                        'price' => 25.00,
                        'discount' => 7.00,
                        'quantity' => 1,
                    ],
                    [
                        'price' => 32.00,
                        'discount' => 0.00,
                        'quantity' => 2,
                    ],
                ],
                'expectedTotalRevenue' => 89.00, // 25 + 32*2
            ],
        ];
    }

    // Helper methods

    protected function createProductStub(string $id, float $price): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getPrice')->willReturn($price);

        return $product;
    }

    protected function createItemStub(Product $product, float $salePrice, int $qtyToAdd): Item
    {
        $item = $this->createStub(Item::class);
        $item->method('getProduct')->willReturn($product);
        $item->method('getData')
            ->willReturnMap([
                ['base_price', null, $salePrice],
                ['qty_to_add', null, $qtyToAdd],
            ]);
        $item->method('getPrice')->willReturn($salePrice);

        return $item;
    }

    protected function createOrderItems(array $itemsData): array
    {
        $items = [];
        foreach ($itemsData as $data) {
            $product = $this->createStub(Product::class);
            $product->method('getId')->willReturn($data['id']);

            $item = $this->createStub(OrderItem::class);
            $item->method('getProduct')->willReturn($product);
            $item->method('getPrice')->willReturn($data['price']);
            $item->method('getOriginalPrice')->willReturn($data['originalPrice']);
            $item->method('getDiscountAmount')->willReturn($data['cartDiscountAmount']);
            $item->method('getQtyOrdered')->willReturn($data['qtyOrdered']);

            $items[] = $item;
        }

        return $items;
    }

    protected function createOrderItemWithQueryId(string $id, ?string $queryId, float $price, int $qty): OrderItem
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn($id);

        $item = $this->createMock(OrderItem::class);
        $item->method('getProduct')->willReturn($product);
        $item->method('getPrice')->willReturn($price);
        $item->method('getOriginalPrice')->willReturn($price);
        $item->method('getDiscountAmount')->willReturn(0.0);
        $item->method('getQtyOrdered')->willReturn($qty);

        if ($queryId !== null) {
            $item->method('hasData')->with(InsightsHelper::QUOTE_ITEM_QUERY_PARAM)->willReturn(true);
            $item->method('getData')->with(InsightsHelper::QUOTE_ITEM_QUERY_PARAM)->willReturn($queryId);
        } else {
            $item->method('hasData')->with(InsightsHelper::QUOTE_ITEM_QUERY_PARAM)->willReturn(false);
        }

        return $item;
    }
}
