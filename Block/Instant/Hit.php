<?php

namespace Algolia\AlgoliaSearch\Block\Instant;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\View\Element\Template;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Customer\Model\Context as CustomerContext;

class Hit extends Template
{
    protected $config;
    protected $priceKey;
    protected $httpContext;

    public function __construct(
        Template\Context $context,
        ConfigHelper $config,
        HttpContext $httpContext,
        array $data = []
    ) {
        $this->config = $config;
        $this->httpContext = $httpContext;

        parent::__construct($context, $data);
    }

    public function getPriceKey()
    {
        if ($this->priceKey === null) {
            $groupId = $this->getGroupId();
            $currencyCode = $this->_storeManager->getStore()->getCurrentCurrencyCode();
            $this->priceKey = $this->config->isCustomerGroupsEnabled($this->_storeManager->getStore()->getStoreId()) ? '.' . $currencyCode . '.group_' . $groupId : '.' . $currencyCode . '.default';
        }

        return $this->priceKey;
    }

    public function getGroupId()
    {
        return $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);
    }
}
