<?php

namespace Algolia\AlgoliaSearch\Model\Observer\Insights;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CheckoutCartProductAddBefore implements ObserverInterface
{
    /** @var ConfigHelper */
    private $configHelper;

    /** @var InsightsHelper */
    private $insightsHelper;

    /**
     * @param ConfigHelper $configHelper
     * @param InsightsHelper $insightsHelper
     */
    public function __construct(
        ConfigHelper $configHelper,
        InsightsHelper $insightsHelper
    ) {
        $this->configHelper = $configHelper;
        $this->insightsHelper = $insightsHelper;
    }

    /**
     * @param Observer $observer
     * ['info' => $requestInfo, 'product' => $product]
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getProduct();
        $requestInfo = $observer->getEvent()->getInfo();

        if (!$this->configHelper->isClickConversionAnalyticsEnabled($product->getStoreId())
            || $this->configHelper->getConversionAnalyticsMode($product->getStoreId()) !== 'place_order') {
            return;
        }

        if (isset($requestInfo['queryID']) && $requestInfo['queryID'] == '') {
            $product->setData('queryId', $requestInfo['queryID']);
        }
    }
}
