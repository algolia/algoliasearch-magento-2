<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class PriceKeyResolver
{
    /** @var array<string, string> */
    protected array $priceKeys = [];

    public function __construct(
        protected ConfigHelper $config,
        protected StoreManagerInterface $storeManager,
        protected HttpContext $httpContext
    )
    {}

    /**
     * @throws NoSuchEntityException
     */
    public function getPriceKey(int $storeId): string
    {
        $groupId = $this->getGroupId($storeId);
        $cacheKey = $storeId . '_' . $groupId;

        if (!isset($this->priceKeys[$cacheKey])) {
            $store = $this->storeManager->getStore($storeId);
            $currencyCode = $store->getCurrentCurrencyCode();

            $this->priceKeys[$cacheKey] = '.' . $currencyCode . '.' . $groupId;
        }

        return $this->priceKeys[$cacheKey];
    }

    protected function getGroupId(int $storeId): string
    {
        return $this->config->isCustomerGroupsEnabled($storeId)
            ? 'group_' . $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP)
            : 'default';
    }
}
