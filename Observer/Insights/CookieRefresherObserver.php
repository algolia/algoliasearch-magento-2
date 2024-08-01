<?php

namespace Algolia\AlgoliaSearch\Observer\Insights;

use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CookieRefresherObserver implements ObserverInterface
{
    /**
     * CookieRefresherObserver observer constructor.
     *
     * @param CustomerSession  $customerSession
     * @param InsightsHelper $insightsHelper
     *
     */
    public function __construct(
        private readonly CustomerSession  $customerSession,
        private readonly InsightsHelper $insightsHelper,
    ) {}

    /**
     * Renew anonymous or customer session token to update the lifetime
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if ($this->customerSession->isLoggedIn()) {
            $this->insightsHelper->setAuthenticatedUserToken($this->customerSession->getCustomer());
        } else {
            $this->insightsHelper->setNonAuthenticatedUserToken();
        }
    }
}
