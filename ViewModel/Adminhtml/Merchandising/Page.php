<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml\Merchandising;

use Algolia\AlgoliaSearch\Helper\ProxyHelper;

class Page {

    /** @var ProxyHelper */
    private $proxyHelper;

    public function __construct(
        ProxyHelper $proxyHelper
    ) {
        $this->proxyHelper = $proxyHelper;
    }

    /**
     * @return bool
     */
    public function canAccessLandingPageBuilder()
    {
        $planLevel = 1;

        $planLevelInfo = $this->proxyHelper->getInfo(ProxyHelper::INFO_TYPE_PLAN_LEVEL);
        if (isset($planLevelInfo['plan_level'])) {
            $planLevel = (int) $planLevelInfo['plan_level'];
        }

        return $planLevel > 1;
    }

    /**
     * @return bool
     */
    public function canAccessMerchandisingFeature()
    {
        $clientData = $this->proxyHelper->getClientConfigurationData();
        if (isset($clientData['query_rules'])) {
            return (bool) $clientData['query_rules'];
        }

        return false;
    }
}
