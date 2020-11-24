<?php

namespace Algolia\AlgoliaSearch\Model\Observer\Insights;

use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class CheckoutOnepageControllerSuccessAction implements ObserverInterface
{
    /** @var Data */
    private $dataHelper;

    /** @var InsightsHelper */
    private $insightsHelper;

    /** @var OrderFactory */
    private $orderFactory;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param Data $dataHelper
     * @param InsightsHelper $insightsHelper
     * @param OrderFactory $orderFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Data $dataHelper,
        InsightsHelper $insightsHelper,
        OrderFactory $orderFactory,
        LoggerInterface $logger
    ) {
        $this->dataHelper = $dataHelper;
        $this->insightsHelper = $insightsHelper;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
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

        if (!$order) {
            $orderId = $observer->getEvent()->getOrderIds()[0];
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        }

        if (!$order || !$this->insightsHelper->isOrderPlacedTracked($order->getStoreId())) {
            return $this;
        }

        $userClient = $this->insightsHelper->getUserInsightsClient();
        $orderItems = $order->getAllVisibleItems();

        if ($this->getConfigHelper()->isClickConversionAnalyticsEnabled($order->getStoreId())
            && $this->getConfigHelper()->getConversionAnalyticsMode($order->getStoreId()) === 'place_order') {
            $queryIds = [];
            /** @var \Magento\Sales\Model\Order\Item $item */
            foreach ($orderItems as $item) {
                if ($item->hasData('algoliasearch_query_param')) {
                    $queryId = $item->getData('algoliasearch_query_param');
                    $queryIds[$queryId][] = $item->getProductId();
                }
            }

            if (count($queryIds) > 0) {
                foreach ($queryIds as $queryId => $productIds) {

                    // Event can't process more than 20 objects
                    $productIds = array_slice($productIds, 0, 20);

                    try {
                        $userClient->convertedObjectIDsAfterSearch(
                            __('Placed Order'),
                            $this->dataHelper->getIndexName('_products', $order->getStoreId()),
                            $productIds,
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

                // Event can't process more than 20 objects
                if (count($productIds) > 20) {
                    break;
                }
            }

            try {
                $userClient->convertedObjectIDs(
                    __('Placed Order'),
                    $this->dataHelper->getIndexName('_products', $order->getStoreId()),
                    $productIds
                );
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }

        return $this;
    }
}
