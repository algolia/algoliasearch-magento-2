<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Api\AnalyticsClient;
use Algolia\AlgoliaSearch\Configuration\AnalyticsConfig;
use Algolia\AlgoliaSearch\DataProvider\Analytics\IndexEntityDataProvider;
use Algolia\AlgoliaSearch\Logger\Logger;
use Magento\Framework\Locale\ResolverInterface;

class AnalyticsHelper
{
    public const ANALYTICS_API_PATH_PREFIX      = '2/';
    public const ANALYTICS_SEARCH_PATH          = self::ANALYTICS_API_PATH_PREFIX . 'searches';
    public const ANALYTICS_HITS_PATH            = self::ANALYTICS_API_PATH_PREFIX . 'hits';
    public const ANALYTICS_FILTER_PATH          = self::ANALYTICS_API_PATH_PREFIX . 'filters';
    public const ANALYTICS_CLICKS_PATH          = self::ANALYTICS_API_PATH_PREFIX . 'clicks';
    public const ANALYTICS_CONVERSION_RATE_PATH = self::ANALYTICS_API_PATH_PREFIX . 'conversions';
    public const DATE_FORMAT_PICKER             = 'dd MMM yyyy';
    public const DATE_FORMAT_API                = 'Y-m-d';

    private $searches;
    private $users;
    private $rateOfNoResults;

    private $clickPositions;
    private $clickThroughs;
    private $conversions;
    private $conversionsAddToCart;
    private $conversionsPlaceOrder;

    private $clientData;

    private $errors = [];

    private $fetchError = false;

    /**
     * @var AnalyticsClient
     */
    private $analyticsClient;

    /**
     * @var AnalyticsConfig
     */
    private $analyticsConfig;

    /**
     * Region can be modified via the Magento configuration
     *
     * @var string
     */
    protected $region;

    /**
     * @param ConfigHelper $configHelper
     * @param IndexEntityDataProvider $entityHelper
     * @param Logger $logger
     * @param ResolverInterface $localeResolver
     */
    public function __construct(
        private ConfigHelper            $configHelper,
        private IndexEntityDataProvider $entityHelper,
        private Logger                  $logger,
        private ResolverInterface       $localeResolver
    )
    {
        $this->region = $this->configHelper->getAnalyticsRegion();
    }

    private function setupAnalyticsClient()
    {
        if ($this->analyticsClient) {
            return;
        }


        $this->analyticsClient = AnalyticsClient::create(
            $this->configHelper->getApplicationID(),
            $this->configHelper->getAPIKey(),
            $this->region
        );

        $this->analyticsConfig = AnalyticsConfig::create(
            $this->configHelper->getApplicationID(),
            $this->configHelper->getAPIKey(),
            $this->region
        );
    }

    /**
     * @param $storeId
     *
     * @return array<string, string>
     */
    public function getAnalyticsIndices(int $storeId): array
    {
        return [
            'products'   => $this->entityHelper->getIndexNameByEntity('products', $storeId),
            'categories' => $this->entityHelper->getIndexNameByEntity('categories', $storeId),
            'pages'      => $this->entityHelper->getIndexNameByEntity('pages', $storeId),
        ];
    }

    /**
     * Search Analytics
     *
     * @param array $params
     *
     * @return array<string, mixed>
     */
    public function getTopSearches(array $params): array
    {
        return $this->safeFetch(self::ANALYTICS_SEARCH_PATH, $params);
    }

    /**
     * @param array $params
     * @return array<string, mixed>
     */
    public function getCountOfSearches(array $params): array
    {
        if (!isset($this->searches)) {
            $this->searches = $this->safeFetch(self::ANALYTICS_SEARCH_PATH . '/count', $params);
        }

        return $this->searches;
    }

    /**
     * @param array $params
     * @return int
     */
    public function getTotalCountOfSearches(array $params): int
    {
        $searches = $this->getCountOfSearches($params);

        return $searches && isset($searches['count']) ? $searches['count'] : 0;
    }

    public function getSearchesByDates(array $params)
    {
        $searches = $this->getCountOfSearches($params);

        return $searches && isset($searches['dates']) ? $searches['dates'] : [];
    }

    /**
     * @param array $params
     * @return array<string, mixed>
     */
    public function getTopSearchesNoResults(array $params): array
    {
        return $this->safeFetch(self::ANALYTICS_SEARCH_PATH . '/noResults', $params);
    }

    /**
     * @param array $params
     * @return array<string, mixed>
     */
    public function getRateOfNoResults(array $params): array
    {
        if (!isset($this->rateOfNoResults)) {
            $this->rateOfNoResults = $this->safeFetch(self::ANALYTICS_SEARCH_PATH . '/noResultRate', $params);
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
     *
     * @param array $params
     *
     * @return array<string, mixed>
     */
    public function getTopHits(array $params): array
    {
        return $this->safeFetch(self::ANALYTICS_HITS_PATH, $params);
    }

    /**
     * @param $search
     * @param array $params
     * @return array<string, mixed>
     */
    public function getTopHitsForSearch($search, array $params): array
    {
        return $this->safeFetch(self::ANALYTICS_HITS_PATH . '?search=' . urlencode($search), $params);
    }

    /**
     * Get Count of Users
     *
     * @param array $params
     *
     * @return array<string, mixed>
     */
    public function getUsers(array $params): array
    {
        if (!isset($this->users)) {
            $this->users = $this->safeFetch(self::ANALYTICS_API_PATH_PREFIX . 'users/count', $params);
        }

        return $this->users;
    }

    /**
     * @param array $params
     * @return int
     */
    public function getTotalUsersCount(array $params): int
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
     *
     * @param array $params
     *
     * @return array<string, mixed>
     */
    public function getTopFilterAttributes(array $params): array
    {
        return $this->safeFetch(self::ANALYTICS_FILTER_PATH, $params);
    }

    /**
     * @param string $search
     * @param array $params
     * @return array<string, mixed>
     */
    public function getTopFiltersForANoResultsSearch(string $search, array $params): array
    {
        return $this->safeFetch(self::ANALYTICS_FILTER_PATH . '/noResults?search=' . urlencode($search), $params);
    }

    /**
     * @param string $search
     * @param array $params
     * @return array<string, mixed>
     */
    public function getTopFiltersForASearch(string $search, array $params): array
    {
        return $this->safeFetch(self::ANALYTICS_FILTER_PATH . '?search=' . urlencode($search), $params);
    }

    /**
     * @param array $attributes
     * @param string $search
     * @param array $params
     * @return array<string, mixed>
     */
    public function getTopFiltersForAttributesAndSearch(array $attributes, string $search, array $params): array
    {
        return $this->safeFetch(self::ANALYTICS_FILTER_PATH . '/' . implode(',', $attributes)
            . '?search=' . urlencode($search), $params);
    }

    /**
     * @param string $attribute
     * @param array $params
     * @return array<string, mixed>
     */
    public function getTopFiltersForAttribute(string $attribute, array $params): array
    {
        return $this->safeFetch(self::ANALYTICS_FILTER_PATH . '/' . $attribute, $params);
    }

    /**
     * Click Analytics
     *
     * @param array $params
     *
     * @return array<string, mixed>
     */
    public function getAverageClickPosition(array $params): array
    {
        if (!isset($this->clickPositions)) {
            $this->clickPositions = $this->safeFetch(
                self::ANALYTICS_CLICKS_PATH . '/averageClickPosition',
                $params,
                array_fill_keys(['average', 'clickCount'], null)
            );
        }

        return $this->clickPositions;
    }

    public function getAverageClickPositionByDates(array $params)
    {
        $click = $this->getAverageClickPosition($params);

        return $click && isset($click['dates']) ? $click['dates'] : [];
    }

    /**
     * @param array $params
     * @return array<string,mixed>
     */
    public function getClickThroughRate(array $params): array
    {
        if (!isset($this->clickThroughs)) {
            $this->clickThroughs = $this->safeFetch(
                self::ANALYTICS_CLICKS_PATH . '/clickThroughRate',
                $params,
                array_fill_keys(['rate', 'trackedSearchCount'], null)
            );
        }

        return $this->clickThroughs;
    }

    public function getClickThroughRateByDates(array $params)
    {
        $click = $this->getClickThroughRate($params);

        return $click && isset($click['dates']) ? $click['dates'] : [];
    }

    /**
     * @param array $params
     * @return array<string, mixed>
     */
    public function getConversionRate(array $params): array
    {
        if (!isset($this->conversions)) {
            $this->conversions = $this->getConversionRateCalc($params);
        }

        return $this->conversions;
    }

    /**
     * @param array $params
     * @return array<string, mixed>
     */
    public function getConversionRateAddToCart(array $params): array
    {
        if (!isset($this->conversionsAddToCart)) {
            $this->conversionsAddToCart = $this->getConversionRateCalc($params, 'addToCartRate');
        }

        return $this->conversionsAddToCart;
    }

    /**
     * @param array $params
     * @return array<string, mixed>
     */
    public function getConversionRatePlaceOrder(array $params): array
    {
        if (!isset($this->conversionsPlaceOrder)) {
            $this->conversionsPlaceOrder = $this->getConversionRateCalc($params, 'purchaseRate');
        }

        return $this->conversionsPlaceOrder;
    }

    /**
     * @param array $params
     * @return array<string, mixed>
     */
    private function getConversionRateCalc(array $params, $path = 'conversionRate'): array
    {
        return $this->conversions = $this->safeFetch(
            self::ANALYTICS_CONVERSION_RATE_PATH . '/' . $path,
            $params,
            array_fill_keys(['rate', 'trackedSearchCount'], null)
        );
    }

    public function getConversionRateByDates(array $params)
    {
        $conversion = $this->getConversionRate($params);

        return $conversion && isset($conversion['dates']) ? $conversion['dates'] : [];
    }

    public function isAnalyticsApiEnabled()
    {
        return true;
    }

    public function isClickAnalyticsEnabled()
    {
        return $this->configHelper->isClickConversionAnalyticsEnabled();
    }

    /**
     * Pass through method for handling API Versions
     *
     * @param string $path
     * @param array $params
     *
     * @return array<string, mixed>|false
     */
    private function fetch(string $path, array $params): array|false
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

            $this->setupAnalyticsClient();

            $response = $this->analyticsClient->customGet($path, $params);
        } catch (\Exception $e) {
            // TODO: Revisit this error handling code to provide better feedback to front end with PHP connect v4 API
            $this->errors[] = $e->getMessage() . ': ' . $path;
            $this->logger->log($e->getMessage());

            $this->fetchError = true;
        }

        return $response;
    }

    /**
     * A failed request can return false - this provides a way to specify a default
     * @param string $path
     * @param array $params
     * @param array $default Optional default - if not supplied will return an empty array on failed request
     * @return array<string, mixed>
     */
    protected function safeFetch(string $path, array $params, array $default = []): array {
        $value = $this->fetch($path, $params);
        return $value !== false ? $value : $default;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param string $timezone
     * @return \IntlDateFormatter
     */
    public function getAnalyticsDatePickerFormatter(string $timezone): \IntlDateFormatter
    {
        $locale = $this->localeResolver->getLocale();
        $dateFormatter = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $timezone);
        $dateFormatter->setPattern(self::DATE_FORMAT_PICKER);
        return $dateFormatter;
    }

}
