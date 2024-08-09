<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml\Analytics;

use Algolia\AlgoliaSearch\DataProvider\Analytics\IndexEntityDataProvider;
use Algolia\AlgoliaSearch\Helper\AnalyticsHelper;
use Algolia\AlgoliaSearch\ViewModel\Adminhtml\BackendView;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

class Overview implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    public const LIMIT_RESULTS = 5;

    public const DEFAULT_TYPE = 'products';

    public const DEFAULT_RETENTION_DAYS = 90;

    /** @var array */
    private $analyticsParams = [];

    /**
     * @param BackendView $backendView
     * @param AnalyticsHelper $analyticsHelper
     * @param IndexEntityDataProvider $indexEntityDataProvider
     */
    public function __construct(
        protected BackendView             $backendView,
        protected AnalyticsHelper         $analyticsHelper,
        protected IndexEntityDataProvider $indexEntityDataProvider
    ) { }

    /**
     * @return BackendView
     */
    public function getBackendView()
    {
        return $this->backendView;
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getTimeZone(): string
    {
        return (string) $this->backendView->getDateTime()->getConfigTimezone(
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->getStore()->getId()
        );
    }

    /**
     * @throws NoSuchEntityException
     *
     * @return mixed
     */
    public function getIndexName()
    {
        $sections = $this->getSections();

        return $sections[$this->getCurrentType()];
    }

    /**
     * @param array $additional
     *
     * @throws NoSuchEntityException
     *
     * @return array
     */
    public function getAnalyticsParams(array $additional = []): array
    {
        if (empty($this->analyticsParams)) {
            $params = ['index' => $this->getIndexName()];
            if ($formData = $this->getBackendView()->getBackendSession()->getAlgoliaAnalyticsFormData()) {
                if (!empty($formData['from'])) {
                    $params['startDate'] = $this->formatFormSubmittedDate($formData['from']);
                }
                if (!empty($formData['to'])) {
                    $params['endDate'] =  $this->formatFormSubmittedDate($formData['to']);
                }
            }

            $this->analyticsParams = $params;
        }

        return array_merge($this->analyticsParams, $additional);
    }

    /**
     * @param string $dateString
     * @return string
     * @throws NoSuchEntityException
     */
    protected function formatFormSubmittedDate(string $dateString): string
    {
        $timezone = $this->getTimeZone();
        $dateTime = $this->parseFormSubmittedDate($dateString, $timezone);
        return $dateTime->format(AnalyticsHelper::DATE_FORMAT_API);
    }

    /**
     * @param string|null $dateString
     * @param string|null $timezone
     * @return \DateTime
     * @throws NoSuchEntityException
     */
    protected function parseFormSubmittedDate(string $dateString = null, string $timezone = null): \DateTime
    {
        if (empty($timezone)) {
            $timezone = $this->getTimeZone();
        }

        if (empty($dateString)) {
            return new \DateTime('now', new \DateTimeZone($timezone));
        }

        $dateFormatter = $this->analyticsHelper->getAnalyticsDatePickerFormatter($timezone);
        $parsedDate = $dateFormatter->parse($dateString);
        return (new \DateTime('now', new \DateTimeZone($timezone)))->setTimestamp($parsedDate);
    }

    public function getTotalCountOfSearches()
    {
        return $this->analyticsHelper->getTotalCountOfSearches($this->getAnalyticsParams());
    }

    public function getSearchesByDates()
    {
        return $this->analyticsHelper->getSearchesByDates($this->getAnalyticsParams());
    }

    public function getTotalUsersCount()
    {
        return $this->analyticsHelper->getTotalUsersCount($this->getAnalyticsParams());
    }

    public function getUsersCountByDates()
    {
        return $this->analyticsHelper->getUsersCountByDates($this->getAnalyticsParams());
    }

    public function getTotalResultRates()
    {
        return $this->analyticsHelper->getTotalResultRates($this->getAnalyticsParams());
    }

    public function getResultRateByDates()
    {
        return $this->analyticsHelper->getResultRateByDates($this->getAnalyticsParams());
    }

    /**
     * Click Analytics
     *
     * @throws NoSuchEntityException
     *
     * @return mixed
     */
    public function getClickThroughRate()
    {
        return $this->analyticsHelper->getClickThroughRate($this->getAnalyticsParams());
    }

    public function getClickThroughRateByDates()
    {
        return $this->analyticsHelper->getClickThroughRateByDates($this->getAnalyticsParams());
    }

    public function getConversionRate()
    {
        return $this->analyticsHelper->getConversionRate($this->getAnalyticsParams());
    }

    public function getConversionRateAddToCart()
    {
        return $this->analyticsHelper->getConversionRateAddToCart($this->getAnalyticsParams());
    }

    public function getConversionRatePlaceOrder()
    {
        return $this->analyticsHelper->getConversionRatePlaceOrder($this->getAnalyticsParams());
    }

    public function getConversionRateByDates()
    {
        return $this->analyticsHelper->getConversionRateByDates($this->getAnalyticsParams());
    }

    public function getClickPosition()
    {
        return $this->analyticsHelper->getAverageClickPosition($this->getAnalyticsParams());
    }

    public function getClickPositionByDates()
    {
        return $this->analyticsHelper->getAverageClickPositionByDates($this->getAnalyticsParams());
    }

    /**
     * Get aggregated Daily data from separate calls
     *
     * @return array
     */
    public function getDailySearchData()
    {
        $searches = $this->getSearchesByDates();
        $users = $this->getUsersCountByDates();
        $rates = $this->getResultRateByDates();
        $clickPosition = null;
        $conversion = null;
        $ctr = null;

        if ($this->isClickAnalyticsEnabled()) {
            $clickPosition = $this->getClickPositionByDates();
            $ctr = $this->getClickThroughRateByDates();
            $conversion = $this->getConversionRateByDates();
        }

        foreach ($searches as &$search) {
            $search['users'] = $this->getDateValue($users, $search['date'], 'count');
            $search['rate'] = $this->getDateValue($rates, $search['date'], 'rate');

            if ($this->isClickAnalyticsEnabled()) {
                $search['clickPos'] = $this->getDateValue($clickPosition, $search['date'], 'average');
                $search['ctr'] = $this->getDateValue($ctr, $search['date'], 'rate');
                $search['conversion'] = $this->getDateValue($conversion, $search['date'], 'rate');
            }

            $search['formatted'] = date('M, d', strtotime($search['date']));
        }

        return $searches;
    }

    /**
     * @param $array
     * @param $date
     * @param $valueKey
     *
     * @return string
     */
    private function getDateValue($array, $date, $valueKey)
    {
        $value = '';
        foreach ($array as $item) {
            if ($item['date'] === $date) {
                $value = $item[$valueKey];

                break;
            }
        }

        return $value;
    }

    public function getTopSearches()
    {
        $topSearches = $this->analyticsHelper->getTopSearches(
            $this->getAnalyticsParams(['limit' => self::LIMIT_RESULTS])
        );

        return isset($topSearches['searches']) ? $topSearches['searches'] : [];
    }

    /**
     * @throws LocalizedException
     *
     * @return array
     */
    public function getPopularResults()
    {
        $popular = $this->analyticsHelper->getTopHits($this->getAnalyticsParams(['limit' => self::LIMIT_RESULTS]));
        $hits = isset($popular['hits']) ? $popular['hits'] : [];

        if (!empty($hits)) {
            $objectIds = array_map(function ($arr) {
                return $arr['hit'];
            }, $hits);

            $storeId = $this->getStore()->getId();

            if ($this->getCurrentType() == 'products') {
                $collection = $this->indexEntityDataProvider->getProductCollection($storeId, $objectIds);

                foreach ($hits as &$hit) {
                    $item = $collection->getItemById($hit['hit']);
                    $hit['name'] = $item->getName();
                    $hit['url'] = $item->getProductUrl(false);
                }
            }

            if ($this->getCurrentType() == 'categories') {
                $collection = $this->indexEntityDataProvider->getCategoryCollection($storeId, $objectIds);

                foreach ($hits as &$hit) {
                    $item = $collection->getItemById($hit['hit']);
                    $hit['name'] = $item->getName();
                    $hit['url'] = $item->getUrl();
                }
            }

            if ($this->getCurrentType() == 'pages') {
                $collection = $this->indexEntityDataProvider->getPageCollection($storeId, $objectIds);

                foreach ($hits as &$hit) {
                    $item = $collection->getItemByColumnValue('page_id', $hit['hit']);
                    $hit['name'] = $item->getTitle();
                    $hit['url'] = $this->getBackendView()->getUrlInterface()
                        ->getUrl(null, ['_direct' => $item->getIdentifier()]);
                }
            }
        }

        return $hits;
    }

    /**
     * @throws NoSuchEntityException
     *
     * @return array
     */
    public function getNoResultSearches()
    {
        $noResults = $this->analyticsHelper->getTopSearchesNoResults(
            $this->getAnalyticsParams(['limit' => self::LIMIT_RESULTS])
        );

        return $noResults && isset($noResults['searches']) ? $noResults['searches'] : [];
    }

    /**
     * @throws NoSuchEntityException
     *
     * @return bool
     */
    public function checkIsValidDateRange(): bool
    {
        if ($formData = $this->getBackendView()->getBackendSession()->getAlgoliaAnalyticsFormData()) {
            if (!empty($formData['from'])) {
                $timezone = $this->getTimeZone();

                $startDate = $this->parseFormSubmittedDate($formData['from'], $timezone);
                $now = $this->parseFormSubmittedDate(null, $timezone);
                $diff = date_diff($startDate, $now);

                if ($diff->days > $this->getAnalyticRetentionDays()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return int
     */
    public function getAnalyticRetentionDays()
    {
        $retention = self::DEFAULT_RETENTION_DAYS;

        return $retention;
    }

    /**
     * @return string
     */
    public function getCurrentType()
    {
        if ($formData = $this->getBackendView()->getBackendSession()->getAlgoliaAnalyticsFormData()) {
            if (isset($formData['type'])) {
                return $formData['type'];
            }
        }

        return self::DEFAULT_TYPE;
    }

    /**
     * @throws NoSuchEntityException
     *
     * @return array
     */
    public function getSections()
    {
        return $this->analyticsHelper->getAnalyticsIndices($this->getStore()->getId());
    }

    /**
     * @param $search
     *
     * @return array
     */
    public function getTypeEditUrl($search)
    {
        $links = [];
        if ($this->getCurrentType() == 'products') {
            $links['edit'] = $this->getBackendView()->getUrlInterface()
                ->getUrl('catalog/product/edit', ['id' => $search['hit']]);
        }

        if ($this->getCurrentType() == 'categories') {
            $links['edit'] = $this->getBackendView()->getUrlInterface()
                ->getUrl('catalog/category/edit', ['id' => $search['hit']]);
        }

        if ($this->getCurrentType() == 'pages') {
            $links['edit'] = $this->getBackendView()->getUrlInterface()
                ->getUrl('cms/page/edit', ['page_id' => $search['hit']]);
        }

        if (isset($search['url'])) {
            $links['view'] = $search['url'];
        }

        return $links;
    }

    /**
     * @return string
     */
    public function getAnalyticsConfigurationUrl()
    {
        return $this->getBackendView()->getUrlInterface()
            ->getUrl('adminhtml/system_config/edit/section/algoliasearch_cc_analytics');
    }

    /**
     * @return string
     */
    public function getDailyChartHtml()
    {
        $block = $this->getBackendView()->getLayout()->createBlock(\Magento\Backend\Block\Template::class);
        $block->setTemplate('Algolia_AlgoliaSearch::analytics/graph.phtml');
        $block->setData('analytics', $this->getDailySearchData());

        return $block->toHtml();
    }

    /**
     * @param $message
     *
     * @return string
     */
    public function getTooltipHtml($message)
    {
        return $this->getBackendView()->getTooltipHtml($message);
    }

    /**
     * @throws NoSuchEntityException
     *
     * @return StoreInterface|null|string
     */
    public function getStore()
    {
        if ($storeId = $this->getBackendView()->getRequest()->getParam('store')) {
            return $this->getBackendView()->getStoreManager()->getStore($storeId);
        }

        return $this->getBackendView()->getStoreManager()->getDefaultStoreView();
    }

    /**
     * @return bool|int
     */
    public function isAnalyticsApiEnabled()
    {
        return $this->analyticsHelper->isAnalyticsApiEnabled();
    }

    /**
     * @return bool|int
     */
    public function isClickAnalyticsEnabled()
    {
        return $this->analyticsHelper->isClickAnalyticsEnabled();
    }

    /**
     * Messages rendered HTML getter.
     *
     * @return string
     */
    public function getMessagesHtml()
    {
        /** @var $messagesBlock \Magento\Framework\View\Element\Messages */
        $messagesBlock = $this->getBackendView()->getLayout()
            ->createBlock(\Magento\Framework\View\Element\Messages::class);

        if (!$this->checkIsValidDateRange() && $this->isAnalyticsApiEnabled()) {
            $noticeHtml = __('The selected date is out of your analytics retention window (%1 days),
                your data might not be present anymore.', $this->getAnalyticRetentionDays());
            $noticeHtml .= '<br/>';
            $noticeHtml .= __('To increase your retention and access more data, you could switch to a
                <a href="%1" target="_blank">higher plan.</a>', 'https://www.algolia.com/billing/overview/');

            $messagesBlock->addNotice($noticeHtml);
        }

        $errors = $this->analyticsHelper->getErrors();
        if (!empty($errors)) {
            foreach ($errors as $message) {
                $messagesBlock->addError($message);
            }
        }

        return $messagesBlock->toHtml();
    }
}
