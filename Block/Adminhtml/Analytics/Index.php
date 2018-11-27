<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Analytics;

use Algolia\AlgoliaSearch\Helper\AnalyticsHelper;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollection;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollection;

class Index extends Template
{
    const LIMIT_RESULTS = 5;
    const DEFAULT_TYPE = 'products';
    const DEFAULT_RETENTION_DAYS = 7;

    /** @var Context */
    private $backendContext;

    /** @var AnalyticsHelper */
    private $analyticsHelper;

    /** @var TimezoneInterface */
    private $dateTime;

    /** @var ProductCollection */
    private $productCollection;

    /** @var CategoryCollection */
    private $categoryCollection;

    /** @var PageCollection */
    private $pageCollection;

    private $analyticsParams = [];

    /**
     * Index constructor.
     * @param Context $context
     * @param AnalyticsHelper $analyticsHelper
     * @param TimezoneInterface $dateTime
     * @param ProductCollection $productCollection
     * @param CategoryCollection $categoryCollection
     * @param PageCollection $pageCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        AnalyticsHelper $analyticsHelper,
        TimezoneInterface $dateTime,
        ProductCollection $productCollection,
        CategoryCollection $categoryCollection,
        PageCollection $pageCollection,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->backendContext = $context;
        $this->analyticsHelper = $analyticsHelper;
        $this->dateTime = $dateTime;
        $this->productCollection = $productCollection;
        $this->categoryCollection = $categoryCollection;
        $this->pageCollection = $pageCollection;
    }

    public function getIndexName()
    {
        $sections = $this->getSections();
        return $sections[$this->getCurrentType()];
    }

    /**
     * @param array $additional
     * @return array
     */
    public function getAnalyticsParams($additional = [])
    {
        if (empty($this->analyticsParams)) {
            $params = ['index' => $this->getIndexName()];
            if ($formData = $this->_backendSession->getAlgoliaAnalyticsFormData()) {
                if (isset($formData['from']) && $formData['from'] !== '') {
                    $params['startDate'] = date('Y-m-d', $this->dateTime->date($formData['from'])->getTimestamp());
                }
                if (isset($formData['to']) && $formData['to'] !== '') {
                    $params['endDate'] = date('Y-m-d', $this->dateTime->date($formData['to'])->getTimestamp());
                }
            }
            
            $this->analyticsParams = $params;
        }

        return array_merge($this->analyticsParams, $additional);
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
     */
    public function getDailySearchData()
    {
        $searches = $this->getSearchesByDates();
        $users = $this->getUsersCountByDates();
        $rates = $this->getResultRateByDates();

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

            $date = $this->dateTime->date($search['date']);
            $search['formatted'] = date('M, d', $date->getTimestamp());
        }

        return $searches;
    }

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
            $this->getAnalyticsParams(['limit' => self::LIMIT_RESULTS]));
        return isset($topSearches['searches']) ? $topSearches['searches'] : [];
    }

    public function getPopularResults()
    {
        $popular = $this->analyticsHelper->getTopHits($this->getAnalyticsParams(['limit' => self::LIMIT_RESULTS]));
        $hits = isset($popular['hits']) ? $popular['hits'] : [];

        if (!empty($hits)) {
            $objectIds = array_map(function($arr) {
                return $arr['hit'];
            }, $hits);

            if ($this->getCurrentType() == 'products') {
                $collection = $this->productCollection->create();
                $collection->addAttributeToSelect('name');
                $collection->addAttributeToFilter('entity_id', ['in' => $objectIds]);

                foreach ($hits as &$hit) {
                    $item = $collection->getItemById($hit['hit']);
                    $hit['name'] = $item->getName();
                    $hit['url'] = $item->getProductUrl(false);
                }
            }

            if ($this->getCurrentType() == 'categories') {
                $collection = $this->categoryCollection->create();
                $collection->addAttributeToSelect('name');
                $collection->addAttributeToFilter('entity_id', ['in' => $objectIds]);

                foreach ($hits as &$hit) {
                    $item = $collection->getItemById($hit['hit']);
                    $hit['name'] = $item->getName();
                    $hit['url'] = $item->getUrl();
                }
            }

            if ($this->getCurrentType() == 'pages') {
                $collection = $this->pageCollection->create();
                $collection->addFieldToSelect(['page_id', 'title', 'identifier']);
                $collection->addFieldToFilter('page_id', ['in' => $objectIds]);

                foreach ($hits as &$hit) {
                    $item = $collection->getItemByColumnValue('page_id', $hit['hit']);
                    $hit['name'] = $item->getTitle();
                    $hit['url'] = $this->_urlBuilder->getUrl(null, ['_direct' => $item->getIdentifier()]);
                }
            }
        }

        return $hits;
    }

    public function getNoResultSearches()
    {
        $noResults = $this->analyticsHelper->getTopSearchesNoResults(
            $this->getAnalyticsParams(['limit' => self::LIMIT_RESULTS]));
        return $noResults && isset($noResults['searches']) ? $noResults['searches'] : [];
    }

    public function checkIsValidDateRange()
    {
        if ($formData = $this->_backendSession->getAlgoliaAnalyticsFormData()) {
            if (isset($formData['from']) && !empty($formData['from'])) {

                $startDate = $this->dateTime->date($formData['from']);
                $diff = date_diff($startDate, $this->dateTime->date());

                if ($diff->days > $this->getAnalyticRetentionDays()) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getAnalyticRetentionDays()
    {
        $retention = self::DEFAULT_RETENTION_DAYS;
        $clientData = $this->analyticsHelper->getClientData();
        if (isset($clientData['analytics_retention_days'])) {
            $retention = (int) $clientData['analytics_retention_days'];
        }

        return $retention;
    }

    public function getCurrentType()
    {
        if ($formData = $this->_backendSession->getAlgoliaAnalyticsFormData()) {
            if (isset($formData['type'])) {
                return $formData['type'];
            }
        }
        return self::DEFAULT_TYPE;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSections()
    {
        return $this->analyticsHelper->getAnalyticsIndices($this->getStore()->getId());
    }

    public function getTypeEditUrl($search)
    {
        $links = [];
        if ($this->getCurrentType() == 'products') {
            $links['edit'] = $this->getUrl('catalog/product/edit', ['id' => $search['hit']]);
        }

        if ($this->getCurrentType() == 'categories') {
            $links['edit'] = $this->getUrl('catalog/category/edit', ['id' => $search['hit']]);
        }

        if ($this->getCurrentType() == 'pages') {
            $links['edit'] = $this->getUrl('cms/page/edit', ['page_id' => $search['hit']]);
        }

        if (isset($search['url'])) {
            $links['view'] = $search['url'];
        }

        return $links;
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getDailyChartHtml()
    {
        $block = $this->getLayout()->createBlock(\Magento\Backend\Block\Template::class);
        $block->setTemplate('Algolia_AlgoliaSearch::analytics/graph.phtml');
        $block->setData('analytics', $this->getDailySearchData());
        return $block->toHtml();
    }

    /**
     * @param $message
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getTooltipHtml($message)
    {
        $block = $this->getLayout()->createBlock(\Magento\Backend\Block\Template::class);
        $block->setTemplate('Algolia_AlgoliaSearch::ui/tooltips.phtml');
        $block->setData('message', $message);
        return $block->toHtml();
    }

    /**
     * @return \Magento\Store\Api\Data\StoreInterface|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStore()
    {
        $storeManager = $this->backendContext->getStoreManager();
        if ($storeId = $this->getRequest()->getParam('store')) {
            return $storeManager->getStore($storeId);
        }

        return $storeManager->getDefaultStoreView();
    }
    
    public function isAnalyticsApiEnabled()
    {
        return $this->analyticsHelper->isAnalyticsApiEnabled();
    }

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
        $messagesBlock = $this->_layout->createBlock(\Magento\Framework\View\Element\Messages::class);

        if (!$this->checkIsValidDateRange() && $this->isAnalyticsApiEnabled()) {
            $noticeHtml = __('The selected date is out of your analytics retention window (%1 days), 
                your data might not be present anymore.', $this->getAnalyticRetentionDays());
            $noticeHtml .= '<br/>';
            $noticeHtml .=  __('To increase your retention and access more data, you could switch to a 
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
