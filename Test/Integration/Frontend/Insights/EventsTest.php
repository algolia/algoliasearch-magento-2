<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Frontend\Insights;

use Algolia\AlgoliaSearch\Api\Insights\EventProcessorInterface;
use Algolia\AlgoliaSearch\Api\InsightsClient;
use Algolia\AlgoliaSearch\Service\Insights\EventProcessor;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\StoreManager;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class EventsTest extends TestCase
{
    const PLACED_ORDER_EVENT = 'Placed Order';
    const EVENT_TYPE_CONVERSION = "conversion";
    const EVENT_SUBTYPE_PURCHASE = "purchase";
    const INDEX = 'my-index';
    const TOKEN = 'dummy-token';

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var GuestCartManagementInterface
     */
    protected $cartManagement;

    /**
     * @var CheckoutSession
     */
    protected  $checkoutSession;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /** @var StoreManager */
    protected $storeManager;

    /**
     * @var EventProcessor
     */
    protected $eventProcessor;

    /**
     * @var InsightsClient
     */
    protected $insightsClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();

        $this->cartManagement = $this->objectManager->get(GuestCartManagementInterface::class);
        $this->checkoutSession = $this->objectManager->get(CheckoutSession::class);
        $this->quoteIdMaskFactory = $this->objectManager->get(QuoteIdMaskFactory::class);
        $this->eventProcessor = $this->objectManager->get(EventProcessor::class);

        /** @var StoreManager $storeManager */
        $this->storeManager = $this->objectManager->get(StoreManager::class);

        $this->insightsClient = $this->getMockBuilder(InsightsClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->eventProcessor->setInsightsClient($this->insightsClient);
        $this->eventProcessor->setAnonymousUserToken(self::TOKEN);
        $this->eventProcessor->setStoreManager($this->storeManager);
    }

    /**
     * @param Order $order
     * @param bool $withQueryID
     * @param string $eventName
     * @return array[]
     */
    protected function generateConversionEvent(Order $order, bool $withQueryID = true, string $eventName = self::PLACED_ORDER_EVENT): array
    {
        $event = [
            EventProcessorInterface::EVENT_KEY_SUBTYPE => self::EVENT_SUBTYPE_PURCHASE,
            EventProcessorInterface::EVENT_KEY_OBJECT_IDS => [],
            EventProcessorInterface::EVENT_KEY_OBJECT_DATA => [],
            EventProcessorInterface::EVENT_KEY_CURRENCY => 'USD',
            EventProcessorInterface::EVENT_KEY_VALUE => 0,
            'eventType' => self::EVENT_TYPE_CONVERSION,
            'eventName' => $eventName,
            'index' => self::INDEX,
            'userToken' => self::TOKEN,
        ];

        if ($withQueryID) {
            $event[EventProcessorInterface::EVENT_KEY_QUERY_ID] = '';
        }

        foreach ($order->getItems() as $item) {
            $event[EventProcessorInterface::EVENT_KEY_OBJECT_IDS][] = $item->getProductId();
            $event[EventProcessorInterface::EVENT_KEY_OBJECT_DATA][] = [
                'price' => $item->getPrice(),
                'discount' => $item->getDiscountAmount(),
                'quantity' => $item->getQtyOrdered()
            ];
            $event[EventProcessorInterface::EVENT_KEY_VALUE] += $item->getPrice() * $item->getQtyOrdered();
        }

        return ['events' => [$event]];
    }

    /**
     * @param Order $order
     * @param bool $withQueryID
     * @return void
     */
    protected function assertOrderPurchaseEvent(Order $order, bool $withQueryID = true): void
    {
        $args = [$this->generateConversionEvent($order, $withQueryID), []];

        $this->insightsClient
            ->expects(self::once())
            ->method('pushEvents')
            ->with(...$args)
            ->willReturn([]);

        $this->eventProcessor->convertPurchase(
            self::PLACED_ORDER_EVENT,
            self::INDEX,
            $order
        );
    }

    /**
     * Gets order entity by increment id.
     *
     * @param string $incrementId
     * @return OrderInterface
     */
    protected function getOrder(string $incrementId): OrderInterface
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder->addFilter('increment_id', $incrementId)
            ->create();

        /** @var OrderRepositoryInterface $repository */
        $repository = $this->objectManager->get(OrderRepositoryInterface::class);
        $items = $repository->getList($searchCriteria)
            ->getItems();

        return array_pop($items);
    }

    /**
     * @magentoDataFixture Magento/Sales/_files/guest_quote_with_addresses.php
     */
    public function testQuoteToOrder()
    {
        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class);
        $quote->load('guest_quote', 'reserved_order_id');

        $this->checkoutSession->setQuoteId($quote->getId());

        /** @var QuoteIdMask $quoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create();
        $quoteIdMask->load($quote->getId(), 'quote_id');
        $cartId = $quoteIdMask->getMaskedId();

        /** @var GuestCartManagementInterface $cartManagement */
        $orderId = $this->cartManagement->placeOrder($cartId);
        /** @var Order $order */
        $order = $this->objectManager->get(OrderRepository::class)->get($orderId);

        // GrandTotal takes shipping into account
        $this->assertEquals(15, $order->getGrandTotal());
        $this->assertOrderPurchaseEvent($order, false);
    }

    /**
     * @magentoDataFixture Algolia_AlgoliaSearch::Test/Integration/Frontend/Insights/_files/order_with_two_order_items.php
     */
    public function testOrderWithTwoOrderItems()
    {
        /** @var Order $order */
        $order = $this->getOrder('100000001');

        $this->assertOrderPurchaseEvent($order);
    }
}
