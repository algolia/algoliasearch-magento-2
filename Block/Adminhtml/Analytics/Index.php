<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Analytics;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\AnalyticsHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Index extends Template
{
    /** @var DateTime */
    private $dateTime;

    /** @var AlgoliaHelper */
    private $algoliaHelper;

    /** @var ObjectManagerInterface */
    private $objectManger;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var AnalyticsHelper */
    private $analyticsHelper;

    /** @var Data */
    private $dataHelper;

    /** @var Product */
    private $productHelper;

    /**
     * Index constructor.
     * @param Context $context
     * @param AlgoliaHelper $algoliaHelper
     * @param DateTime $dateTime
     * @param ObjectManagerInterface $objectManager
     * @param ConfigHelper $configHelper
     * @param AnalyticsHelper $analyticsHelper
     * @param ProductHelper $productHelper
     * @param Data $dataHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        AlgoliaHelper $algoliaHelper,
        DateTime $dateTime,
        ObjectManagerInterface $objectManager,
        ConfigHelper $configHelper,
        AnalyticsHelper $analyticsHelper,
        ProductHelper $productHelper,
        Data $dataHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->dateTime = $dateTime;
        $this->algoliaHelper = $algoliaHelper;
        $this->objectManger = $objectManager;
        $this->configHelper = $configHelper;
        $this->dataHelper = $dataHelper;
        $this->productHelper = $productHelper;
        $this->analyticsHelper = $analyticsHelper;
    }

    /**
     * @return string
     */
    public function getIndexName()
    {
        return $this->dataHelper->getIndexName($this->productHelper->getIndexNameSuffix(), $this->getStore()->getId());
    }

    /**
     * @param array $additional
     * @return array
     */
    public function getAnalyticsParams($additional = array())
    {
        // add startDate and endDate optional parameters
        $params = array('index' => $this->getIndexName());
        return array_merge($params, $additional);
    }

    public function getTopSearches()
    {
        $topSearches = $this->analyticsHelper->getTopSearches($this->getAnalyticsParams());
        return isset($topSearches['searches']) ? array_slice($topSearches['searches'], 0, 5) : array();
    }

    public function getNoResultSearches()
    {
        $noResults = $this->analyticsHelper->getTopSearchesNoResults($this->getAnalyticsParams());
        return isset($noResults['searches']) ? array_slice($noResults['searches'], 0, 5) : array();
    }

    /**
     * @return Magento\Store\Model\StoreManagerInterface
     */
    public function getStore()
    {
        $storeManager = $this->objectManger->get('Magento\Store\Model\StoreManagerInterface');
        if ($storeId = $this->getRequest()->getParam('store')) {
            return $storeManager->getStore($storeId);
        }

        return $storeManager->getDefaultStoreView();
    }
}
