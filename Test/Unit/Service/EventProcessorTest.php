<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\InsightsClient;
use Algolia\AlgoliaSearch\Service\Insights\EventProcessor;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class EventProcessorTest extends TestCase
{
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

        $this->eventProcessor = new EventProcessorTestable(
            $this->client,
            $this->userToken,
            $this->authenticatedUserToken,
            $this->storeManager
        );
    }

    /**
     * @dataProvider orderItemsProvider
     */
    public function testObjectDataForPurchase($orderItemsData, $expectedResult, $expectedTotalRevenue): void
    {
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

    public function orderItemsProvider(): array
    {
        return [
            [ // One item
                'orderItemsData' => [
                    [
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
            [ // One item with discount
                'orderItemsData' => [
                    [
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
            [ // Two items
                'orderItemsData' => [
                    [
                        'getPriceInclTax' => 32.00,
                        'getOriginalPrice' => 32.00,
                        'getDiscountAmount' => 7.00,
                        'getQtyOrdered' => 1,
                    ],
                    [
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
