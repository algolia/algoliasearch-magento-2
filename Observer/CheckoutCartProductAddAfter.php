<?php

namespace Algolia\AlgoliaSearch\Observer;

use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Psr\Log\LoggerInterface;

class CheckoutCartProductAddAfter implements ObserverInterface
{
    /** @var Data */
    protected $dataHelper;

    /** @var InsightsHelper */
    protected $insightsHelper;

    /** @var LoggerInterface */
    protected $logger;

    /** @var SessionManagerInterface */
    protected $coreSession;

    /** @var \Algolia\AlgoliaSearch\Helper\ConfigHelper  */
    protected $configHelper;

    /**
     * @param Data $dataHelper
     * @param InsightsHelper $insightsHelper
     * @param LoggerInterface $logger
     * @param SessionManagerInterface $coreSession
     */
    public function __construct(
        Data $dataHelper,
        InsightsHelper $insightsHelper,
        LoggerInterface $logger,
        SessionManagerInterface $coreSession
    ) {
        $this->dataHelper = $dataHelper;
        $this->insightsHelper = $insightsHelper;
        $this->logger = $logger;
        $this->coreSession = $coreSession;
        $this->configHelper = $this->insightsHelper->getConfigHelper();
    }

    /**
     * @param Observer $observer
     * ['quote_item' => $result, 'product' => $product]
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Quote\Model\Quote\Item $quoteItem */
        $quoteItem = $observer->getEvent()->getQuoteItem();
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getProduct();
        $storeId = $quoteItem->getStoreId();
        $conversionAnalyticsMode = $this->configHelper->getConversionAnalyticsMode($storeId);
        $queryId = $this->coreSession->getQueryId();

        if (!$this->insightsHelper->isAddedToCartTracked($storeId) && !$this->insightsHelper->isOrderPlacedTracked($storeId)) {
            return;
        }

        $userClient = $this->insightsHelper->getUserInsightsClient();

        switch ($conversionAnalyticsMode) {
            case 'place_order':
                $quoteItem->setData('algoliasearch_query_param', $queryId);
                break;
            case 'add_to_cart':
                if ($queryId) {
                    try {
                        $userClient->convertedObjectIDsAfterSearch(
                            __('Added to Cart'),
                            $this->dataHelper->getIndexName('_products', $storeId),
                            [$product->getId()],
                            $queryId
                        );
                    } catch (\Exception $e) {
                        $this->logger->critical($e);
                    }
                }
                break;
            default:
                // When Personalization (Add To cart) is turned on and conversion analytics is turned off
                try {
                    $userClient->convertedObjectIDs(
                        __('Added to Cart'),
                        $this->dataHelper->getIndexName('_products', $storeId),
                        [$product->getId()]
                    );
                } catch (\Exception $e) {
                    $this->logger->critical($e);
                }
        }
    }
}
