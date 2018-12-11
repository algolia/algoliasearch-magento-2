<?php

namespace Algolia\AlgoliaSearch\Block\Instant;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\View\Element\Template;

class Hit extends Template
{
    private $config;
    private $priceKey;
    private $httpContext;

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

            /** @var \Magento\Store\Model\Store $store */
            $store = $this->_storeManager->getStore();

            $currencyCode = $store->getCurrentCurrencyCode();
            $this->priceKey = $this->config->isCustomerGroupsEnabled($store->getStoreId())
                ? '.' . $currencyCode . '.group_' . $groupId : '.' . $currencyCode . '.default';
        }

        return $this->priceKey;
    }

    public function getGroupId()
    {
        return $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);
    }
}
