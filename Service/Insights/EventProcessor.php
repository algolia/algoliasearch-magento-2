<?php

namespace Algolia\AlgoliaSearch\Service\Insights;

use Algolia\AlgoliaSearch\Api\Insights\EventProcessorInterface;
use Algolia\AlgoliaSearch\Api\InsightsClient;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\StoreManagerInterface;

class EventProcessor implements EventProcessorInterface
{
    /** @var string  */
    protected const NO_QUERY_ID_KEY = '__NO_QUERY_ID__';

    public function __construct(
        protected ?InsightsClient        $client = null,
        protected ?string                $userToken = null,
        protected ?string                $authenticatedUserToken = null,
        protected ?StoreManagerInterface $storeManager = null
    ) {}

    public function setInsightsClient(InsightsClient $client): EventProcessorInterface
    {
        $this->client = $client;
        return $this;
    }

    public function setAnonymousUserToken(string $token): EventProcessorInterface
    {
        $this->userToken = $token;
        return $this;
    }

    public function setAuthenticatedUserToken(string $token): EventProcessorInterface
    {
        $this->authenticatedUserToken = $token;
        return $this;
    }

    public function setStoreManager(StoreManagerInterface $storeManager): EventProcessorInterface
    {
        $this->storeManager = $storeManager;
        return $this;
    }

    /**
     * @return void
     * @throws AlgoliaException
     */
    private function checkDependencies(): void
    {
        if (
            !$this->client
            || !$this->userToken
            || !$this->storeManager
        ) {
            throw new AlgoliaException("Events model is missing necessary dependencies to function.");
        }
    }

    /**
     * @inheritDoc
     */
    public function convertedObjectIDsAfterSearch(string $eventName, string $indexName, array $objectIDs, string $queryID, array $requestOptions = []): array
    {
        $this->checkDependencies();

        $event = [
            self::EVENT_KEY_OBJECT_IDS => $objectIDs,
            self::EVENT_KEY_QUERY_ID   => $queryID,
        ];

        return $this->converted($event, $eventName, $indexName, $requestOptions);
    }

    /**
     * @inheritDoc
     */
    public function convertedObjectIDs(string $eventName, string $indexName, array $objectIDs, array $requestOptions = []): array
    {
        $this->checkDependencies();
        return $this->converted(['objectIDs' => $objectIDs], $eventName, $indexName, $requestOptions);
    }

    private function convertedBatch(array $eventsBatch, string $eventName, string $indexName, array $requestOptions = []): array
    {
        return $this->client->pushEvents(
            ['events' => $this->decorateEvents($eventsBatch, $eventName, $indexName)],
            $requestOptions
        );
    }

    private function converted(array $event, string $eventName, string $indexName, array $requestOptions = []): array
    {
        return $this->convertedBatch([$event], $eventName, $indexName, $requestOptions);
    }

    private function decorateEvents(array $events, string $eventName, string $indexName): array
    {
        return array_map(function(array $event) use ($eventName, $indexName)  {
            if ($this->authenticatedUserToken) {
                $event['authenticatedUserToken'] = $this->authenticatedUserToken;
            }

            return array_merge($event, [
                'eventType' => 'conversion',
                'eventName' => $eventName,
                'index'     => $indexName,
                'userToken' => $this->userToken
            ]);
        }, $events);
    }

    /**
     * @return string
     * @throws LocalizedException if unable to find store or currency
     */
    private function getCurrentCurrency(): string
    {
        return $this->storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * @inheritDoc
     */
    public function convertAddToCart(string $eventName, string $indexName, Item $item, string $queryID = null): array
    {
        $this->checkDependencies();

        $price = $this->getQuoteItemSalePrice($item);
        $qty = intval($item->getData('qty_to_add'));

        $event = [
            self::EVENT_KEY_SUBTYPE     => self::EVENT_SUBTYPE_CART,
            self::EVENT_KEY_OBJECT_IDS  => [$item->getProduct()->getId()],
            self::EVENT_KEY_OBJECT_DATA => [[
                'price'    => $price,
                'discount' => $this->getQuoteItemDiscount($item),
                'quantity' => $qty
            ]],
            self::EVENT_KEY_CURRENCY    => $this->getCurrentCurrency(),
            self::EVENT_KEY_VALUE       => $price * $qty
        ];

        if ($queryID) {
            $event[self::EVENT_KEY_QUERY_ID] = $queryID;
        }

        return $this->converted($event, $eventName, $indexName, []);
    }

    /**
     * @inheritDoc
     */
    public function convertPurchaseForItems(string $eventName, string $indexName, array $items, string $queryID = null): array
    {
        $this->checkDependencies();

        $objectData = $this->getObjectDataForPurchase($items);

        $event = [
            self::EVENT_KEY_SUBTYPE     => self::EVENT_SUBTYPE_PURCHASE,
            self::EVENT_KEY_OBJECT_IDS  => $this->restrictMaxObjectsPerEvent($this->getObjectIdsForPurchase($items)),
            self::EVENT_KEY_OBJECT_DATA => $this->restrictMaxObjectsPerEvent($objectData),
            self::EVENT_KEY_CURRENCY    => $this->getCurrentCurrency(),
            self::EVENT_KEY_VALUE       => $this->getTotalRevenueForEvent($objectData)
        ];

        if ($queryID) {
            $event[self::EVENT_KEY_QUERY_ID] = $queryID;
        }

        return $this->converted($event, $eventName, $indexName, []);
    }

    /**
     * @inheritDoc
     */
    public function convertPurchase(string $eventName, string $indexName, Order $order): array
    {
        $this->checkDependencies();

        $itemsByQueryId = $this->getItemsByQueryId($order);
        $eventBatch = [];

        foreach ($itemsByQueryId as $queryId => $items) {
            $objectData = $this->getObjectDataForPurchase($items);
            $event = [
                self::EVENT_KEY_SUBTYPE     => self::EVENT_SUBTYPE_PURCHASE,
                self::EVENT_KEY_OBJECT_IDS  => $this->restrictMaxObjectsPerEvent($this->getObjectIdsForPurchase($items)),
                self::EVENT_KEY_OBJECT_DATA => $this->restrictMaxObjectsPerEvent($objectData),
                self::EVENT_KEY_CURRENCY    => $this->getCurrentCurrency(),
                self::EVENT_KEY_VALUE       => $this->getTotalRevenueForEvent($objectData)
            ];

            if ($queryId !== self::NO_QUERY_ID_KEY) {
                $event[self::EVENT_KEY_QUERY_ID] = $queryId;
            }
            $eventBatch[] = $event;
        }

        $resp = [];
        foreach (array_chunk($eventBatch, self::MAX_EVENTS_PER_REQUEST) as $chunk) {
            $resp[] = $this->convertedBatch($chunk, $eventName, $indexName);
        }

        return $resp;
    }

    /**
     * There is a limit to the number of objects that can be attached to a single event.
     * That limit * (MAX_OBJECT_IDS_PER_EVENT) is applied but the total value for the event
     * should still take into account the truncated items.
     *
     * TODO: Implement chunking if exceeding the limit is a common use case
     *
     * @param array $items
     * @return array
     */
    protected function restrictMaxObjectsPerEvent(array $items): array
    {
        return array_slice($items, 0, self::MAX_OBJECT_IDS_PER_EVENT);
    }

    /**
     * Call this before enforcing the object limit (MAX_OBJECT_IDS_PER_EVENT)
     * to ensure full revenue capture in the value argument.
     *
     * @param array<array<string, mixed>> $objectData
     * @return float Total revenue
     */
    protected function getTotalRevenueForEvent(array $objectData): float
    {
        return array_reduce($objectData, function($carry, $item) {
           return floatval($carry) + floatval($item['quantity']) * floatval($item['price']);
        });
    }

    /**
     * Price / final price does not return applied customer group pricing
     * so check base price first which returns the desired value.
     *
     * @param Item $item
     * @return float
     */
    protected function getQuoteItemSalePrice(Item $item): float
    {
        return floatval($item->getData('base_price') ?? $item->getPrice());
    }

    /**
     * @param Item $item
     * @return float
     */
    protected function getQuoteItemDiscount(Item $item): float
    {
        return floatval($item->getProduct()->getPrice()) - $this->getQuoteItemSalePrice($item);
    }

    /**
     * @param OrderItem $item
     * @return float
     */
    protected function getOrderItemSalePrice(OrderItem $item): float
    {
        return floatval($item->getPrice()) - $this->getOrderItemCartDiscount($item);
    }

    /**
     * @param OrderItem $item
     * @return float
     */
    protected function getOrderItemCartDiscount(OrderItem $item): float
    {
        return floatval($item->getDiscountAmount()) / intval($item->getQtyOrdered());
    }

    /**
     * @param OrderItem $item
     * @return float
     */
    protected function getOrderItemDiscount(OrderItem $item): float
    {
        $itemDiscount = floatval($item->getOriginalPrice()) - floatval($item->getPrice());
        return $itemDiscount + $this->getOrderItemCartDiscount($item);
    }

    /**
     * Extract Item into event object data.
     * Note that we must preserve redundancies because Magento indexes at the parent configurable level
     * and different prices can result on variants for the same Algolia `objectID`
     *
     * @param OrderItem[] $items
     * @return array<array<string, mixed>>
     */
    protected function getObjectDataForPurchase(array $items): array
    {
        return array_map(function($item) {
            return [
                'price'    => $this->getOrderItemSalePrice($item),
                'discount' => $this->getOrderItemDiscount($item),
                'quantity' => intval($item->getQtyOrdered())
            ];
        }, $items);
    }

    /**
     * @param OrderItem[] $items
     * @return int[]
     */
    protected function getObjectIdsForPurchase(array $items): array
    {
        return array_map(function($item) {
            return $item->getProduct()->getId();
        }, $items);
    }


    /**
     * For a given Magento Order return an array of Items grouped by query ID.
     * If no query ID is found group this under self::NO_QUERY_ID_KEY
     * (while `null` could be used this may lead to unexpected behavior)
     * @param Order $order
     * @return array<string, array<OrderItem[]>
     * NOTE: Items are not deduplicated so that aggregate revenue can be calculated by events model as needed
     */
    protected function getItemsByQueryId(Order $order): array
    {
        $itemsByQueryId = [];
        /** @var OrderItem $item */
        foreach ($order->getAllVisibleItems() as $item) {
            if ($item->hasData(InsightsHelper::QUOTE_ITEM_QUERY_PARAM)) {
                $queryId = $item->getData(InsightsHelper::QUOTE_ITEM_QUERY_PARAM);
                $itemsByQueryId[$queryId][] = $item;
            }
            else {
                $itemsByQueryId[self::NO_QUERY_ID_KEY][] = $item;
            }
        }

        return $itemsByQueryId;
    }
}
