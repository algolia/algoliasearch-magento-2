<?php

namespace Algolia\AlgoliaSearch\Model\Observer\ClickAnalytics;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CatalogControllerProductInitBefore implements ObserverInterface
{
    protected $analyticsParams = [
        'queryID',
        'indexName',
        'objectID',
    ];

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
     * @param array $params
     *
     * @return bool
     */
    protected function hasRequiredParameters($params = [])
    {
        foreach ($this->analyticsParams as $requiredParam) {
            if (!isset($params[$requiredParam])) {
                return false;
            }
        }

        return true;
    }

    public function execute(Observer $observer)
    {
        $controllerAction = $observer->getEvent()->getControllerAction();
        $params = $controllerAction->getRequest()->getParams();

        $storeID = $this->checkoutSession->getQuote()->getStoreId();
        if ($this->hasRequiredParameters($params)
            && $this->configHelper->isClickConversionAnalyticsEnabled($storeID)
            && $this->configHelper->getConversionAnalyticsMode($storeID) === 'place_order'
        ) {
            $conversionData = [
                'queryID' => $params['queryID'],
                'indexName' => $params['indexName'],
                'objectID' => $params['objectID'],
            ];

            $this->checkoutSession->setData('algoliasearch_query_param', json_encode($conversionData));
        }
    }
}
