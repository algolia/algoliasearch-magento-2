<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Api\Data\OrderItemInterface;

class QuoteItem
{
    /**
     * QuoteItem plugin constructor.
     *
     */
    public function __construct(
        protected InsightsHelper $insightsHelper
    ) {}

    public function afterConvert(
        ToOrderItem $subject,
        OrderItemInterface $orderItem,
        AbstractItem $item,
        array $additional = []
    ): OrderItemInterface
    {
        $product = $item->getProduct();
        if ($this->insightsHelper->isOrderPlacedTracked($product->getStoreId())) {
            $orderItem->setData(InsightsHelper::QUOTE_ITEM_QUERY_PARAM, $item->getData(InsightsHelper::QUOTE_ITEM_QUERY_PARAM));
        }

        return $orderItem;
    }
}
