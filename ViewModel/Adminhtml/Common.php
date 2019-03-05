<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\ProxyHelper;

class Common
{
    /** @var ProxyHelper */
    private $proxyHelper;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var array */
    private $videosConfig = [
        'algoliasearch_credentials' => [
            'title' => 'How to change a setting',
            'url' => 'https://www.youtube.com/watch?v=7yqOMb2SHw0',
            'thumbnail' => 'https://img.youtube.com/vi/7yqOMb2SHw0/mqdefault.jpg',
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
        'algoliasearch_synonyms' => [
            'title' => 'Notable features',
            'url' => 'https://www.youtube.com/watch?v=qzaLrHz67U4',
            'thumbnail' => 'https://img.youtube.com/vi/qzaLrHz67U4/mqdefault.jpg',
        ],
        'algoliasearch_cc_analytics' => [
            'title' => 'Notable features',
            'url' => 'https://www.youtube.com/watch?v=qzaLrHz67U4',
            'thumbnail' => 'https://img.youtube.com/vi/qzaLrHz67U4/mqdefault.jpg',
        ],
    ];

    /** @var array */
    private $videoInstallation = [
        'title' => 'Installation & Setup',
        'url' => 'https://www.youtube.com/watch?v=twEj_VBWxp8',
        'thumbnail' => 'https://img.youtube.com/vi/twEj_VBWxp8/mqdefault.jpg',
    ];

    public function __construct(
        ProxyHelper $proxyHelper,
        ConfigHelper $configHelper
    ) {
        $this->proxyHelper = $proxyHelper;
        $this->configHelper = $configHelper;
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

        // If the credentials are not set, display the installation video
        if (!$this->configHelper->getApplicationID()
            || !$this->configHelper->getAPIKey()
            || !$this->configHelper->getSearchOnlyAPIKey()) {
            $config = $this->videoInstallation;
        }

        return $config;
    }
}
