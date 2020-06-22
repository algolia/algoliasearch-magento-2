<?php

namespace Algolia\AlgoliaSearch\Model\Observer\Insights;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CustomerLogin implements ObserverInterface
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
     * ['customer' => $customer]
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $observer->getEvent()->getCustomer();

        if ($this->insightsHelper->getPersonalizationHelper()->isPersoEnabled($customer->getStoreId())) {
            $this->insightsHelper->setUserToken($customer);
        }
    }
}
