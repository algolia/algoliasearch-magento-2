<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Api\Data\OrderItemInterface;

class QuoteItem
{
    /**
     * QuoteItem plugin constructor.
     *
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        protected InsightsHelper $insightsHelper
    ) {}

    /**
     * @param ToOrderItem $subject
     * @param OrderItemInterface $orderItem
     * @param AbstractItem $item
     * @param array $additional
     *
     * @return OrderItemInterface
     */
    public function afterConvert(
        ToOrderItem $subject,
        OrderItemInterface $orderItem,
        AbstractItem $item,
        array $additional = []): OrderItemInterface
    {
        $product = $item->getProduct();
        if ($this->insightsHelper->isOrderPlacedTracked($product->getStoreId())) {
            $orderItem->setData(InsightsHelper::QUOTE_ITEM_QUERY_PARAM, $item->getData(InsightsHelper::QUOTE_ITEM_QUERY_PARAM));
        }

        return $orderItem;
    }
}
