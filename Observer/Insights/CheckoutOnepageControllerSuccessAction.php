<?php

namespace Algolia\AlgoliaSearch\Observer\Insights;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class CheckoutOnepageControllerSuccessAction implements ObserverInterface
{
    /** @var Data */
    protected $dataHelper;

    /** @var InsightsHelper */
    protected $insightsHelper;

    /** @var OrderFactory */
    protected $orderFactory;

    /** @var LoggerInterface */
    protected $logger;

    /** @var ConfigHelper  */
    protected $configHelper;

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
        $this->configHelper = $this->insightsHelper->getConfigHelper();
    }

    /**
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
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

        if ($this->configHelper->isClickConversionAnalyticsEnabled($order->getStoreId())
            && $this->configHelper->getConversionAnalyticsMode($order->getStoreId()) === InsightsHelper::CONVERSION_ANALYTICS_MODE_PURCHASE) {
            $queryIds = [];
            /** @var Item $item */
            foreach ($orderItems as $item) {
                if ($item->hasData(InsightsHelper::QUOTE_ITEM_QUERY_PARAM)) {
                    $queryId = $item->getData(InsightsHelper::QUOTE_ITEM_QUERY_PARAM);
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
                            array_unique($productIds),
                            $queryId
                        );
                    } catch (Exception $e) {
                        $this->logger->critical($e);
                        continue; // skip item
                    }
                }
            }
        } else {
            $productIds = [];
            /** @var Item $item */
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
                    array_unique($productIds)
                );
            } catch (Exception $e) {
                $this->logger->critical($e);
            }
        }

        return $this;
    }
}
