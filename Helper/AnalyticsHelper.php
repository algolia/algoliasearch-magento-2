<?php

namespace Algolia\AlgoliaSearch\Helper;

use AlgoliaSearch\Analytics;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;

class AnalyticsHelper extends Analytics
{
    const ANALYTICS_SEARCH_PATH = '/2/searches';
    const ANALYTICS_HITS_PATH = '/2/hits';
    const ANALYTICS_FILTER_PATH = '/2/filters';
    const ANALYTICS_CLICKS_PATH = '/2/clicks';

    const INTERNAL_API_PROXY_URL = 'https://lj1hut7upg.execute-api.us-east-2.amazonaws.com/dev/';

    /** @var \Algolia\AlgoliaSearch\Helper\AlgoliaHelper */
    private $algoliaHelper;

    /** @var \Algolia\AlgoliaSearch\Helper\ConfigHelper */
    private $configHelper;

    /** @var Data */
    private $dataHelper;

    /** @var Product */
    private $productHelper;

    /** @var CategoryHelper */
    private $categoryHelper;

    /** @var PageHelper */
    private $pageHelper;

    private $logger;
    
    /** Cache variables to prevent excessive calls */
    protected $_searches;
    protected $_users;
    protected $_rateOfNoResults;

    protected $_clickPositions;
    protected $_clickThroughs;
    protected $_conversions;

    protected $_clientData;

    protected $_errors = array();

    public function __construct(
        AlgoliaHelper $algoliaHelper,
        ConfigHelper $configHelper,
        Data $dataHelper,
        ProductHelper $productHelper,
        CategoryHelper $categoryHelper,
        PageHelper $pageHelper,
        Logger $logger
    ) {
        $this->algoliaHelper = $algoliaHelper;
        $this->configHelper = $configHelper;

        $this->dataHelper = $dataHelper;
        $this->productHelper = $productHelper;
        $this->categoryHelper = $categoryHelper;
        $this->pageHelper = $pageHelper;

        $this->logger = $logger;

        parent::__construct($algoliaHelper->getClient());
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getAnalyticsIndices($storeId)
    {
        return $sections = array(
            'products' => $this->dataHelper->getIndexName($this->productHelper->getIndexNameSuffix(), $storeId),
            'categories' => $this->dataHelper->getIndexName($this->categoryHelper->getIndexNameSuffix(), $storeId),
            'pages' => $this->dataHelper->getIndexName($this->pageHelper->getIndexNameSuffix(), $storeId)
        );
    }

    /**
     * Search Analytics
     *
     * @param array $params
     * @return mixed
     */
    public function getTopSearches(array $params)
    {
        return $this->fetch(self::ANALYTICS_SEARCH_PATH, $params);
    }

    public function getCountOfSearches(array $params)
    {
        if (!$this->_searches) {
            $this->_searches = $this->fetch(self::ANALYTICS_SEARCH_PATH . '/count', $params);
        }
        return $this->_searches;
    }

    public function getTotalCountOfSearches(array $params)
    {
        $searches = $this->getCountOfSearches($params);
        return $searches && isset($searches['count']) ? $searches['count'] : 0;
    }

    public function getSearchesByDates(array $params)
    {
        $searches = $this->getCountOfSearches($params);
        return $searches && isset($searches['dates']) ? $searches['dates'] : array();
    }

    public function getTopSearchesNoResults(array $params)
    {
        return $this->fetch(self::ANALYTICS_SEARCH_PATH . '/noResults', $params);
    }

    public function getRateOfNoResults(array $params)
    {
        if (!$this->_rateOfNoResults) {
            $this->_rateOfNoResults = $this->fetch(self::ANALYTICS_SEARCH_PATH . '/noResultRate', $params);
        }
        return $this->_rateOfNoResults;
    }

    public function getTotalResultRates(array $params)
    {
        $result = $this->getRateOfNoResults($params);
        return $result && isset($result['rate']) ? round($result['rate'] * 100, 2) . '%' : 0;
    }

    public function getResultRateByDates(array $params)
    {
        $result = $this->getRateOfNoResults($params);
        return $result && isset($result['dates']) ? $result['dates'] : array();
    }

    /**
     * Hits Analytics
     *
     * @param array $params
     * @return mixed
     */
    public function getTopHits(array $params)
    {
        return $this->fetch(self::ANALYTICS_HITS_PATH, $params);
    }

    public function getTopHitsForSearch($search, array $params)
    {
        return $this->fetch(self::ANALYTICS_HITS_PATH . '?search=' . urlencode($search), $params);
    }

    /**
     * Get Count of Users
     *
     * @param array $params
     * @return mixed
     */
    public function getUsers(array $params)
    {
        if (!$this->_users) {
            $this->_users = $this->fetch('/2/users/count', $params);
        }
        return $this->_users;
    }

    public function getTotalUsersCount(array $params)
    {
        $users = $this->getUsers($params);
        return $users && isset($users['count']) ? $users['count'] : 0;
    }

    public function getUsersCountByDates(array $params)
    {
        $users = $this->getUsers($params);
        return $users && isset($users['dates']) ? $users['dates'] : array();
    }

    /**
     * Filter Analytics
     *
     * @param array $params
     * @return mixed
     */
    public function getTopFilterAttributes(array $params)
    {
        return $this->fetch(self::ANALYTICS_FILTER_PATH, $params);
    }

    public function getTopFiltersForANoResultsSearch($search, array $params)
    {
        return $this->fetch(self::ANALYTICS_FILTER_PATH . '/noResults?search=' . urlencode($search), $params);
    }

    public function getTopFiltersForASearch($search, array $params)
    {
        return $this->fetch(self::ANALYTICS_FILTER_PATH . '?search=' . urlencode($search), $params);
    }

    public function getTopFiltersForAttributesAndSearch(array $attributes, $search, array $params)
    {
        return $this->fetch(self::ANALYTICS_FILTER_PATH . '/' . implode(',',
                $attributes) . '?search=' . urlencode($search), $params);
    }

    public function getTopFiltersForAttribute($attribute, array $params)
    {
        return $this->fetch(self::ANALYTICS_FILTER_PATH . '/' . $attribute, $params);
    }

    /**
     * Click Analytics
     *
     * @param array $params
     * @return mixed
     */
    public function getAverageClickPosition(array $params)
    {
        if (!$this->_clickPositions) {
            $this->_clickPositions = $this->fetch(self::ANALYTICS_CLICKS_PATH . '/averageClickPosition', $params);
        }

        return $this->_clickPositions;
    }

    public function getAverageClickPositionByDates(array $params)
    {
        $click = $this->getAverageClickPosition($params);
        return $click && isset($click['dates']) ? $click['dates'] : array();
    }

    public function getClickThroughRate(array $params)
    {
        if (!$this->_clickThroughs) {
            $this->_clickThroughs = $this->fetch(self::ANALYTICS_CLICKS_PATH . '/clickThroughRate', $params);
        }

        return $this->_clickThroughs;
    }

    public function getClickThroughRateByDates(array $params)
    {
        $click = $this->getClickThroughRate($params);
        return $click && isset($click['dates']) ? $click['dates'] : array();
    }

    public function getConversionRate(array $params)
    {
        if (!$this->_conversions) {
            $this->_conversions = $this->fetch('/2/conversions/conversionRate', $params);
        }

        return $this->_conversions;
    }

    public function getConversionRateByDates(array $params)
    {
        $conversion = $this->getConversionRate($params);
        return $conversion && isset($conversion['dates']) ? $conversion['dates'] : array();
    }

    /**
     * Client Data Check
     *
     * @return mixed
     */
    public function getClientData()
    {
        if (!$this->_clientData) {
            $this->_clientData = $this->getClientSettings();
        }
        return $this->_clientData;
    }

    public function isAnalyticsApiEnabled()
    {
        $clientData = $this->getClientData();
        return $clientData && isset($clientData['analytics_api']) ? $clientData['analytics_api'] : 0;
    }

    public function isClickAnalyticsEnabled()
    {
        if (!$this->configHelper->isClickConversionAnalyticsEnabled()) {
            return false;
        }

        $clientData = $this->getClientData();
        return $clientData && isset($clientData['click_analytics']) ? $clientData['click_analytics'] : 0;
    }

    /**
     * Pass through method for handling API Versions
     *
     * @param string $path
     * @param array $params
     * @return mixed
     */
    protected function fetch($path, array $params)
    {
        $response = false;

        try {
            // analytics api requires index name for all calls
            if (!isset($params['index'])) {
                throw new \Exception('Algolia Analytics API requires an index name.');
            }

            $response = $this->request('GET', $path, $params);

        } catch (\Exception $e) {
            $this->_errors[] = $e->getMessage();
            $this->logger->log($e->getMessage());
        }

        return $response;
    }

    public function getErrors()
    {
        return $this->_errors;
    }

    public function getClientSettings()
    {
        $appId = $this->configHelper->getApplicationID();
        $apiKey = $this->configHelper->getAPIKey();

        $token = $appId . ':' . $apiKey;
        $token = base64_encode($token);
        $token = str_replace(["\n", '='], '', $token);
        $params = array(
            'appId' => $appId,
            'token' => $token,
            'type' => 'analytics',
        );
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::INTERNAL_API_PROXY_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         
        $res = curl_exec($ch);
        curl_close ($ch);
        
        if ($res) {
            $res = json_decode($res, true);
        }
        
        return $res;
    }
}
