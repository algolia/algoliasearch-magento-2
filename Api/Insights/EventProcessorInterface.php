<?php

namespace Algolia\AlgoliaSearch\Api\Insights;

use Algolia\AlgoliaSearch\Api\InsightsClient;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

interface EventProcessorInterface
{
    /** @var string */
    public const EVENT_KEY_SUBTYPE = 'eventSubtype';
    /** @var string */
    public const EVENT_KEY_OBJECT_IDS = 'objectIDs';
    /** @var string */
    public const EVENT_KEY_OBJECT_DATA = 'objectData';
    /** @var string */
    public const EVENT_KEY_CURRENCY = 'currency';
    /** @var string */
    public const EVENT_KEY_VALUE = 'value';
    /** @var string */
    public const EVENT_KEY_QUERY_ID = 'queryID';

    /** @var string */
    public const EVENT_SUBTYPE_CART = 'addToCart';
    /** @var string */
    public const EVENT_SUBTYPE_PURCHASE = 'purchase';

    // https://www.algolia.com/doc/rest-api/insights/#method-param-objectids
    /** @var int */
    public const MAX_OBJECT_IDS_PER_EVENT = 20;

    // https://www.algolia.com/doc/rest-api/insights/#events-endpoints
    /** @var int */
    public const MAX_EVENTS_PER_REQUEST = 1000;

    public function setInsightsClient(InsightsClient $client): EventProcessorInterface;

    public function setAuthenticatedUserToken(string $token): EventProcessorInterface;

    public function setAnonymousUserToken(string $token): EventProcessorInterface;

    /**
     * @deprecated Store Manager now handled as injected dependency
     */
    public function setStoreManager(StoreManagerInterface $storeManager): EventProcessorInterface;

    /**
     * @throws AlgoliaException
     *
     * @return array<string, mixed> API response
     */
    public function convertedObjectIDsAfterSearch(
        string $eventName,
        string $indexName,
        array  $objectIDs,
        string $queryID,
        array  $requestOptions = []
    ): array;

    /**
     * @throws AlgoliaException
     *
     * @return array<string, mixed> API response
     */
    public function convertedObjectIDs(
        string $eventName,
        string $indexName,
        array  $objectIDs,
        array  $requestOptions = []
    ): array;

    /**
     * Track conversion for add to cart operation
     *
     * @param string|null $queryID specify if conversion is result of a search
     *
     * @throws AlgoliaException
     * @throws LocalizedException
     *
     * @return array<string, mixed> API response
     */
    public function convertAddToCart(
        string $eventName,
        string $indexName,
        Item   $item,
        ?string $queryID = null
    ): array;

    /**
     * Track purchase conversion for all items on an order in as few batches as possible
     *
     * @throws AlgoliaException
     * @throws LocalizedException
     *
     * @return array<array<string, mixed>> An array of API responses for all batches processed
     */
    public function convertPurchase(
        string $eventName,
        string $indexName,
        Order  $order
    ): array;

    /**
     * Track purchase conversion event for an arbitrary group of items
     *
     * @param Order\Item[] $items
     *
     * @throws AlgoliaException
     * @throws LocalizedException
     *
     * @return array<string, mixed> API response
     */
    public function convertPurchaseForItems(
        string $eventName,
        string $indexName,
        array  $items,
        ?string $queryID = null
    ): array;

}
