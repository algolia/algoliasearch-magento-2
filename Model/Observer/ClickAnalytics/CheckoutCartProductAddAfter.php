<?php

namespace Algolia\AlgoliaSearch\Model\Observer\ClickAnalytics;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CheckoutCartProductAddAfter implements ObserverInterface
{
    /** @var ConfigHelper */
    private $configHelper;

    /** @var CheckoutSession */
    private $checkoutSession;

    /**
     * CatalogControllerProductInitBefore constructor.
     *
     * @param ConfigHelper $configHelper
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        ConfigHelper $configHelper,
        CheckoutSession $checkoutSession
    ) {
        $this->configHelper = $configHelper;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Quote\Model\Quote\Item $quoteItem */
        $quoteItem = $observer->getEvent()->getQuoteItem();
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getProduct();

        if ($this->configHelper->isClickConversionAnalyticsEnabled($product->getStoreId())
            && $this->configHelper->getConversionAnalyticsMode($product->getStoreId()) === 'place_order'
        ) {
            $quoteItem->setData('algoliasearch_query_param', $this->checkoutSession->getData('algoliasearch_query_param'));
        }
    }
}
