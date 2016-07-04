<?php

namespace Algolia\AlgoliaSearch\Block\Autocomplete;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session;

class Product extends Template
{
    protected $config;
    protected $customerSession;
    protected $priceKey;

    public function __construct(
        Template\Context $context,
        ConfigHelper $config,
        Session $customerSession,
        array $data = []
    ) {
        $this->config = $config;
        $this->customerSession = $customerSession;
        parent::__construct($context, $data);
    }

    public function getPriceKey()
    {
        if ($this->priceKey === null) {
            $groupId = $this->customerSession->getCustomer()->getGroupId();
            $currencyCode = $this->_storeManager->getStore()->getCurrentCurrencyCode();
            $this->priceKey = $this->config->isCustomerGroupsEnabled($this->_storeManager->getStore()->getStoreId()) ? '.'.$currencyCode.'.group_'.$groupId : '.'.$currencyCode.'.default';
        }

        return $this->priceKey;
    }
}
