<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data as CoreHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Store\Model\StoreManagerInterface;

class ToolbarPlugin
{
    private $configHelper;
    private $coreHelper;
    private $productHelper;
    private $storeManager;
    private $httpContext;

    public function __construct(
        ConfigHelper $configHelper,
        CoreHelper $coreHelper,
        ProductHelper $productHelper,
        StoreManagerInterface $storeManager,
        HttpContext $httpContext
    ) {
        $this->configHelper = $configHelper;
        $this->coreHelper = $coreHelper;
        $this->productHelper = $productHelper;
        $this->storeManager = $storeManager;
        $this->httpContext = $httpContext;
    }

    // Override the available orders when backend rendering is activated (get the sorting indices)
    public function afterGetAvailableOrders(Toolbar $subject, $result)
    {
        $storeId = $this->storeManager->getStore()->getId();

        if ($this->configHelper->isBackendRenderingEnabled($storeId)) {
            $customerGroupId = $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);
            $indexName = $this->coreHelper->getIndexName($this->productHelper->getIndexNameSuffix());
            $sortingIndices = $this->configHelper->getSortingIndices($indexName, $storeId, $customerGroupId);
            $availableOrders = [];
            $availableOrders[$indexName] = __('Relevance');
            foreach ($sortingIndices as $sortingIndice) {
                $availableOrders[$sortingIndice['name']] = $sortingIndice['label'];
            }
            $result = $availableOrders;
        }

        return $result;
    }
}
