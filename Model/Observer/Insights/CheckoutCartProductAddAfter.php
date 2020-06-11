<?php

namespace Algolia\AlgoliaSearch\Model\Observer\Insights;

use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CheckoutCartProductAddAfter implements ObserverInterface
{
    /** @var Data */
    private $dataHelper;

    /** @var InsightsHelper */
    private $insightsHelper;

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
     * ['quote_item' => $result, 'product' => $product]
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Quote\Model\Quote\Item $quoteItem */
        $quoteItem = $observer->getEvent()->getQuoteItem();
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getProduct();
        $storeId = $quoteItem->getStoreId();

        if ($this->getConfigHelper()->isClickConversionAnalyticsEnabled($storeId)
            && $this->getConfigHelper()->getConversionAnalyticsMode($storeId) === 'place_order'
            && $product->hasData('queryId')) {
            $quoteItem->setData('algoliasearch_query_param', $product->getData('queryId'));
        }

        if (!$this->insightsHelper->isAddedToCartTracked($storeId)) {
            return;
        }

        $userClient = $this->insightsHelper->getUserInsightsClient();

        if ($this->getConfigHelper()->isClickConversionAnalyticsEnabled($storeId)
            && $this->getConfigHelper()->getConversionAnalyticsMode($storeId) === 'add_to_cart') {
            if ($product->hasData('queryId')) {
                $userClient->convertedObjectIDsAfterSearch(
                    __('Added to Cart'),
                    $this->dataHelper->getIndexName('_products', $storeId),
                    [$product->getId()],
                    $product->getData('queryId')
                );
            }
        } else {
            $userClient->convertedObjectIDs(
                __('Added to Cart'),
                $this->dataHelper->getIndexName('_products', $storeId),
                [$product->getId()]
            );
        }
    }
}
