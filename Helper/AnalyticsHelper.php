<?php

namespace Algolia\AlgoliaSearch\Helper;

use AlgoliaSearch\Analytics;
use Algolia\AlgoliaSearch\Helper\Entity\AggregatorHelper;

class AnalyticsHelper extends Analytics
{
    const ANALYTICS_SEARCH_PATH = '/2/searches';
    const ANALYTICS_HITS_PATH = '/2/hits';
    const ANALYTICS_FILTER_PATH = '/2/filters';
    const ANALYTICS_CLICKS_PATH = '/2/clicks';

    const INTERNAL_API_PROXY_URL = 'https://magento-proxy.algolia.com/';

    /** @var AlgoliaHelper */
    private $algoliaHelper;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var AggregatorHelper */
    private $entityHelper;

    /** @var Logger */
    private $logger;

    private $searches;
    private $users;
    private $rateOfNoResults;

    private $clickPositions;
    private $clickThroughs;
    private $conversions;

    private $clientData;

    private $errors = [];

    private $fetchError = false;

    /**
     * AnalyticsHelper constructor.
     * @param AlgoliaHelper $algoliaHelper
     * @param ConfigHelper $configHelper
     * @param AggregatorHelper $entityHelper
     * @param Logger $logger
     */
    public function __construct(
        AlgoliaHelper $algoliaHelper,
        ConfigHelper $configHelper,
        AggregatorHelper $entityHelper,
        Logger $logger
    ) {
        $this->algoliaHelper = $algoliaHelper;
        $this->configHelper = $configHelper;

        $this->entityHelper = $entityHelper;

        $this->logger = $logger;

        parent::__construct($algoliaHelper->getClient());
    }

    /**
     * @param $storeId
     * @return array
     */
    public function getAnalyticsIndices($storeId)
    {
        return $sections = [
            'products' => $this->entityHelper->getIndexNameByEntity('products', $storeId),
            'categories' => $this->entityHelper->getIndexNameByEntity('categories', $storeId),
            'pages' => $this->entityHelper->getIndexNameByEntity('pages', $storeId),
        ];
    }

    /**
     * Search Analytics
     * @param array $params
     * @return mixed
     */
    public function getTopSearches(array $params)
    {
        return $this->fetch(self::ANALYTICS_SEARCH_PATH, $params);
    }

    public function getCountOfSearches(array $params)
    {
        if (!$this->searches) {
            $this->searches = $this->fetch(self::ANALYTICS_SEARCH_PATH . '/count', $params);
        }

        return $this->searches;
    }

    public function getTotalCountOfSearches(array $params)
    {
        $searches = $this->getCountOfSearches($params);

        return $searches && isset($searches['count']) ? $searches['count'] : 0;
    }

    public function getSearchesByDates(array $params)
    {
        $searches = $this->getCountOfSearches($params);

        return $searches && isset($searches['dates']) ? $searches['dates'] : [];
    }

    public function getTopSearchesNoResults(array $params)
    {
        return $this->fetch(self::ANALYTICS_SEARCH_PATH . '/noResults', $params);
    }

    public function getRateOfNoResults(array $params)
    {
        if (!$this->rateOfNoResults) {
            $this->rateOfNoResults = $this->fetch(self::ANALYTICS_SEARCH_PATH . '/noResultRate', $params);
        }

        return $this->rateOfNoResults;
    }

    public function getTotalResultRates(array $params)
    {
        $result = $this->getRateOfNoResults($params);

        return $result && isset($result['rate']) ? round($result['rate'] * 100, 2) . '%' : 0;
    }

    public function getResultRateByDates(array $params)
    {
        $result = $this->getRateOfNoResults($params);

        return $result && isset($result['dates']) ? $result['dates'] : [];
    }

    /**
     * Hits Analytics
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
     * @param array $params
     * @return mixed
     */
    public function getUsers(array $params)
    {
        if (!$this->users) {
            $this->users = $this->fetch('/2/users/count', $params);
        }

        return $this->users;
    }

    public function getTotalUsersCount(array $params)
    {
        $users = $this->getUsers($params);

        return $users && isset($users['count']) ? $users['count'] : 0;
    }

    public function getUsersCountByDates(array $params)
    {
        $users = $this->getUsers($params);

        return $users && isset($users['dates']) ? $users['dates'] : [];
    }

    /**
     * Filter Analytics
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
        return $this->fetch(self::ANALYTICS_FILTER_PATH . '/' . implode(',', $attributes)
            . '?search=' . urlencode($search), $params);
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
        if (!$this->clickPositions) {
            $this->clickPositions = $this->fetch(self::ANALYTICS_CLICKS_PATH . '/averageClickPosition', $params);
        }

        return $this->clickPositions;
    }

    public function getAverageClickPositionByDates(array $params)
    {
        $click = $this->getAverageClickPosition($params);

        return $click && isset($click['dates']) ? $click['dates'] : [];
    }

    public function getClickThroughRate(array $params)
    {
        if (!$this->clickThroughs) {
            $this->clickThroughs = $this->fetch(self::ANALYTICS_CLICKS_PATH . '/clickThroughRate', $params);
        }

        return $this->clickThroughs;
    }

    public function getClickThroughRateByDates(array $params)
    {
        $click = $this->getClickThroughRate($params);

        return $click && isset($click['dates']) ? $click['dates'] : [];
    }

    public function getConversionRate(array $params)
    {
        if (!$this->conversions) {
            $this->conversions = $this->fetch('/2/conversions/conversionRate', $params);
        }

        return $this->conversions;
    }

    public function getConversionRateByDates(array $params)
    {
        $conversion = $this->getConversionRate($params);

        return $conversion && isset($conversion['dates']) ? $conversion['dates'] : [];
    }

    /**
     * Client Data Check
     * @return mixed
     */
    public function getClientData()
    {
        if (!$this->clientData) {
            $this->clientData = $this->getClientSettings();
        }

        return $this->clientData;
    }

    public function isAnalyticsApiEnabled()
    {
        $clientData = $this->getClientData();

        return (bool)$clientData && isset($clientData['analytics_api']) ? $clientData['analytics_api'] : 0;
    }


    public function isClickAnalyticsEnabled()
    {
        if (!$this->configHelper->isClickConversionAnalyticsEnabled()) {
            return false;
        }

        $clientData = $this->getClientData();

        return (bool)$clientData && isset($clientData['click_analytics']) ? $clientData['click_analytics'] : 0;
    }

    /**
     * Pass through method for handling API Versions
     * @param string $path
     * @param array $params
     * @return mixed
     */
    private function fetch($path, array $params)
    {
        $response = false;
        if ($this->fetchError) {
            return $response;
        }

        try {
            // analytics api requires index name for all calls
            if (!isset($params['index'])) {
                $msg = __('Algolia Analytics API requires an index name.');
                throw new \Magento\Framework\Exception\LocalizedException($msg);
            }

            $response = $this->request('GET', $path, $params);

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            $this->logger->log($e->getMessage());

            $this->fetchError = true;
        }

        return $response;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getClientSettings()
    {
        $appId = $this->configHelper->getApplicationID();
        $apiKey = $this->configHelper->getAPIKey();

        $token = $appId . ':' . $apiKey;
        $token = base64_encode($token);
        $token = str_replace(["\n", '='], '', $token);

        $params = [
            'appId' => $appId,
            'token' => $token,
            'type' => 'analytics'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::INTERNAL_API_PROXY_URL . 'get-info/');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $res = curl_exec($ch);
        curl_close($ch);

        if ($res) {
            $res = json_decode($res, true);
        }

        return $res;
    }
}
