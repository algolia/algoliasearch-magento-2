<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml;

use Algolia\AlgoliaSearch\Helper\ProxyHelper;

class Common
{
    /** @var ProxyHelper */
    private $proxyHelper;

    public function __construct(ProxyHelper $proxyHelper)
    {
        $this->proxyHelper = $proxyHelper;
    }

    /** @return bool */
    public function isQueryRulesEnabled()
    {
        $info = $this->proxyHelper->getInfo(ProxyHelper::INFO_TYPE_QUERY_RULES);

        // In case the call to API proxy fails,
        // be "nice" and return true
        if ($info && array_key_exists('query_rules', $info)) {
            return $info['query_rules'];
        }

        return true;
    }

    /** @return bool */
    public function isClickAnalyticsEnabled()
    {
        $info = $this->proxyHelper->getInfo(ProxyHelper::INFO_TYPE_ANALYTICS);

        // In case the call to API proxy fails,
        // be "nice" and return true
        if ($info && array_key_exists('click_analytics', $info)) {
            return $info['click_analytics'];
        }

        return true;
    }
}
