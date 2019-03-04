<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml;

use Algolia\AlgoliaSearch\Helper\ProxyHelper;

class Common
{
    /** @var ProxyHelper */
    private $proxyHelper;

    /** @var array */
    private $videosConfig = [
        'algoliasearch_credentials' => [
            'title' => 'Installation & Setup',
            'url' => 'https://www.youtube.com/watch?v=twEj_VBWxp8',
            'thumbnail' => 'https://img.youtube.com/vi/twEj_VBWxp8/mqdefault.jpg',
        ],
        'algoliasearch_autocomplete' => [
            'title' => 'Autocomplete menu configuration',
            'url' => 'https://www.youtube.com/watch?v=S6yuPl-bsFQ',
            'thumbnail' => 'https://img.youtube.com/vi/S6yuPl-bsFQ/mqdefault.jpg',
        ],
        'algoliasearch_instant' => [
            'title' => 'Instantsearch page configuration',
            'url' => 'https://www.youtube.com/watch?v=-gy92Pbwb64',
            'thumbnail' => 'https://img.youtube.com/vi/-gy92Pbwb64/mqdefault.jpg',
        ],
        'algoliasearch_products' => [
            'title' => 'Product search configuration',
            'url' => 'https://www.youtube.com/watch?v=6XJ11UdgVPE',
            'thumbnail' => 'https://img.youtube.com/vi/6XJ11UdgVPE/mqdefault.jpg',
        ],
        'algoliasearch_queue' => [
            'title' => 'The indexing queue',
            'url' => 'https://www.youtube.com/watch?v=0V1BSKlCm10',
            'thumbnail' => 'https://img.youtube.com/vi/0V1BSKlCm10/mqdefault.jpg',
        ],
    ];

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

    /** @return array|void */
    public function getVideoConfig($section)
    {
        $config = null;

        if (isset($this->videosConfig[$section])) {
            $config = $this->videosConfig[$section];
        }

        return $config;
    }
}
