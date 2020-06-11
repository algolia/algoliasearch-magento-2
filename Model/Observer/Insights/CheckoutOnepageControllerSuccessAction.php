<?php

namespace Algolia\AlgoliaSearch\Model\Observer\Insights;

use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CheckoutOnepageControllerSuccessAction implements ObserverInterface
{
    /** @var Data */
    private $dataHelper;

    /** @var InsightsHelper */
    private $insightsHelper;

    /**
     * @param Data $dataHelper
     * @param InsightsHelper $insightsHelper
     */
    public function __construct(
        Data $dataHelper,
        InsightsHelper $insightsHelper
    ) {
        $this->dataHelper = $dataHelper;
        $this->insightsHelper = $insightsHelper;
    }

    /**
     * @return \Algolia\AlgoliaSearch\Helper\ConfigHelper
     */
    public function getConfigHelper()
    {
        return $this->insightsHelper->getConfigHelper();
    }

    /**
     * @param Observer $observer
     *
     * @return $this|void
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        if (!$this->insightsHelper->isOrderPlacedTracked($order->getStoreId())) {
            return $this;
        }

        $userClient = $this->insightsHelper->getUserInsightsClient();
        $orderItems = $order->getAllVisibleItems();

        if ($this->getConfigHelper()->isClickConversionAnalyticsEnabled($order->getStoreId())
            && $this->getConfigHelper()->getConversionAnalyticsMode($order->getStoreId()) === 'place_order') {

            /** @var \Magento\Sales\Model\Order\Item $item */
            foreach ($orderItems as $item) {
                if ($item->hasData('algoliasearch_query_param')) {
                    try {
                        $queryId = $item->getData('algoliasearch_query_param');
                        $userClient->convertedObjectIDsAfterSearch(
                            __('Placed Order'),
                            $this->dataHelper->getIndexName('_products', $order->getStoreId()),
                            [$item->getProductId()],
                            $queryId
                        );
                    } catch (\Exception $e) {
                        continue; // skip item
                    }
                }
            }
        } else {
            $productIds = [];
            /** @var \Magento\Sales\Model\Order\Item $item */
            foreach ($orderItems as $item) {
                $productIds[] = $item->getProductId();
            }

            $userClient->convertedObjectIDs(
                __('Placed Order'),
                $this->dataHelper->getIndexName('_products', $order->getStoreId()),
                $productIds
            );
        }

        return $this;
    }
}
