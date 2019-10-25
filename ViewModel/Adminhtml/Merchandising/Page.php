<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml\Merchandising;

use Algolia\AlgoliaSearch\Helper\ProxyHelper;

class Page implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    /** @var ProxyHelper */
    private $proxyHelper;

    public function __construct(ProxyHelper $proxyHelper)
    {
        $this->proxyHelper = $proxyHelper;
    }

    /**
     * @return bool
     */
    public function canAccessLandingPageBuilder()
    {
        $clientData = $this->proxyHelper->getClientConfigurationData();
        $planLevel = (isset($clientData['plan_level']) ? (int) $clientData['plan_level'] : 1);

        return $planLevel > 1;
    }

    /**
     * @return bool
     */
    public function canAccessMerchandisingFeature()
    {
        $clientData = $this->proxyHelper->getClientConfigurationData();

        return isset($clientData['query_rules']) ? (bool) $clientData['query_rules'] : false;
    }
}
