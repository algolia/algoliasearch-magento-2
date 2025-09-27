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
}
