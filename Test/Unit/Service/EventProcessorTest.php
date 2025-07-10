<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\InsightsClient;
use Algolia\AlgoliaSearch\Service\Insights\EventProcessor;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Config as TaxConfig;
use PHPUnit\Framework\TestCase;

class EventProcessorTest extends TestCase
{
    protected ?TaxConfig $taxConfig;
    protected ?InsightsClient $client;
    protected ?string $userToken;
    protected ?string $authenticatedUserToken;
    protected ?StoreManagerInterface $storeManager;
    protected ?EventProcessor $eventProcessor;

    protected function setUp(): void
    {
        $this->client = $this->createMock(InsightsClient::class);
        $this->userToken = 'foo';
        $this->authenticatedUserToken = 'authenticated-foo';
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);
        $this->taxConfig =  $this->createMock(TaxConfig::class);

        $this->eventProcessor = new EventProcessorTestable(
            $this->taxConfig,
            $this->client,
            $this->userToken,
            $this->authenticatedUserToken,
            $this->storeManager
        );
    }

    /**
     * @dataProvider orderItemsProvider
     */
    public function testObjectDataForPurchase($priceIncludesTax, $orderItemsData, $expectedResult, $expectedTotalRevenue): void
    {
        $this->taxConfig->method('priceIncludesTax')->willReturn($priceIncludesTax);

        $orderItems = [];

        foreach ($orderItemsData as $orderItemData) {
            $orderItem = $this->getMockBuilder(OrderItem::class)
                ->disableOriginalConstructor()
                ->getMock();

            foreach ($orderItemData as $method => $value){
                $orderItem->method($method)->willReturn($value);
            }

            $orderItems[] = $orderItem;
        }

        $object = $this->eventProcessor->getObjectDataForPurchase($orderItems);
        $this->assertEquals($expectedResult, $object);

        $totalRevenue = $this->eventProcessor->getTotalRevenueForEvent($object);
        $this->assertEquals($expectedTotalRevenue, $totalRevenue);
    }

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
                    ]
                ],
                'expectedResult' => [
                    [
                        'price' => 32.00,
                        'discount' => 0.00,
                        'quantity' => 1,
                    ]
                ],
                'expectedTotalRevenue' => 32.00
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
                    ]
                ],
                'expectedResult' => [
                    [
                        'price' => 25.00,
                        'discount' => 0.00,
                        'quantity' => 1,
                    ]
                ],
                'expectedTotalRevenue' => 25.00
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
                    ]
                ],
                'expectedResult' => [
                    [
                        'price' => 25.00,
                        'discount' => 7.00,
                        'quantity' => 1,
                    ]
                ],
                'expectedTotalRevenue' => 25.00
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
                    ]
                ],
                'expectedResult' => [
                    [
                        'price' => 18.00,
                        'discount' => 7.00,
                        'quantity' => 1,
                    ]
                ],
                'expectedTotalRevenue' => 18.00
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
                    ]
                ],
                'expectedTotalRevenue' => 89.00 // 25 + 32*2
            ],
        ];
    }
}
