<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Query;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Query;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Session\SessionManagerInterface;

class Merchandising extends \Magento\Backend\Block\Template
{
    /** @var string */
    protected $_template = 'query/edit/merchandising.phtml';

    /** @var SessionManagerInterface */
    protected $backendSession;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var Data */
    private $coreHelper;

    /** @var \Magento\Store\Model\StoreManagerInterface */
    private $storeManager;

    public function __construct(
        Context $context,
        SessionManagerInterface $backendSession,
        ConfigHelper $configHelper,
        Data $coreHelper,
        array $data = []
    ) {
        $this->backendSession = $backendSession;
        $this->configHelper = $configHelper;
        $this->coreHelper = $coreHelper;
        $this->storeManager = $context->getStoreManager();

        parent::__construct($context, $data);
    }

    /**
     * @return Query | null
     */
    public function getCurrentQuery()
    {
        return $this->backendSession->getData('algoliasearch_query');
    }

    /**
     * @return ConfigHelper
     */
    public function getConfigHelper()
    {
        return $this->configHelper;
    }

    /**
     * @return Data
     */
    public function getCoreHelper()
    {
        return $this->coreHelper;
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     *
     * @return \Magento\Store\Api\Data\StoreInterface|null
     */
    public function getCurrentStore()
    {
        if ($storeId = $this->getRequest()->getParam('store')) {
            return $this->storeManager->getStore($storeId);
        }

        return $this->storeManager->getDefaultStoreView();
    }
}
