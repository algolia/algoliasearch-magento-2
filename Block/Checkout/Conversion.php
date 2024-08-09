<?php

namespace Algolia\AlgoliaSearch\Block\Checkout;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order\Item;

class Conversion extends Template
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @param Context $context
     * @param Session $checkoutSession
     * @param ConfigHelper $configHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        ConfigHelper $configHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->configHelper = $configHelper;
    }

    protected function getOrderItems()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->checkoutSession->getLastRealOrder();

        return $order->getAllVisibleItems();
    }

    public function getOrderItemsConversionJson()
    {
        $orderItemsData = [];
        $orderItems = $this->getOrderItems();

        /** @var Item $item */
        foreach ($orderItems as $item) {
            if ($item->hasData(InsightsHelper::QUOTE_ITEM_QUERY_PARAM)) {
                $orderItemsData[$item->getProductId()] = json_decode($item->getData(InsightsHelper::QUOTE_ITEM_QUERY_PARAM));
            }
        }

        return json_encode($orderItemsData);
    }

    public function toHtml()
    {
        $storeId = $this->checkoutSession->getLastRealOrder()->getStoreId();
        if ($this->configHelper->isClickConversionAnalyticsEnabled($storeId)
            && $this->configHelper->getConversionAnalyticsMode($storeId) === InsightsHelper::CONVERSION_ANALYTICS_MODE_PURCHASE
        ) {
            return parent::toHtml();
        }

        return '';
    }
}
