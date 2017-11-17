<?php

namespace Algolia\AlgoliaSearch\Block;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Locale\Currency;
use Magento\Framework\Registry;
use Magento\Framework\Url\Helper\Data;
use Magento\Framework\View\Element\Template;
use Magento\Search\Helper\Data as CatalogSearchHelper;
use Magento\Customer\Model\GroupManagement;

class Algolia extends Template implements \Magento\Framework\Data\CollectionDataSourceInterface
{
    protected $config;
    protected $catalogSearchHelper;
    protected $customerSession;
    protected $storeManager;
    protected $objectManager;
    protected $registry;
    protected $productHelper;
    protected $currency;
    protected $algoliaHelper;
    protected $urlHelper;
    protected $formKey;

    protected $priceKey;

    public function __construct(
        Template\Context $context,
        ConfigHelper $config,
        CatalogSearchHelper $catalogSearchHelper,
        Session $customerSession,
        ProductHelper $productHelper,
        Currency $currency,
        Registry $registry,
        AlgoliaHelper $algoliaHelper,
        Data $urlHelper,
        FormKey $formKey,
        array $data = []
    ) {
        $this->config = $config;
        $this->catalogSearchHelper = $catalogSearchHelper;
        $this->customerSession = $customerSession;
        $this->productHelper = $productHelper;
        $this->currency = $currency;
        $this->registry = $registry;
        $this->algoliaHelper = $algoliaHelper;
        $this->urlHelper = $urlHelper;
        $this->formKey = $formKey;

        parent::__construct($context, $data);
    }

    public function getConfigHelper()
    {
        return $this->config;
    }

    public function getProductHelper()
    {
        return $this->productHelper;
    }

    public function getCatalogSearchHelper()
    {
        return $this->catalogSearchHelper;
    }

    public function getAlgoliaHelper()
    {
        return $this->algoliaHelper;
    }

    public function getCurrencySymbol()
    {
        return $this->currency->getCurrency($this->getCurrencyCode())->getSymbol();
    }
    public function getCurrencyCode()
    {
        return $this->_storeManager->getStore()->getCurrentCurrencyCode();
    }

    public function getGroupId()
    {
        return $this->customerSession->isLoggedIn() ? $this->customerSession->getCustomer()->getGroupId() : GroupManagement::NOT_LOGGED_IN_ID;
    }

    public function getPriceKey()
    {
        if ($this->priceKey === null) {
            $groupId = $this->getGroupId();
            $currencyCode = $this->getCurrencyCode();
            $this->priceKey = $this->config->isCustomerGroupsEnabled($this->_storeManager->getStore()->getStoreId()) ? '.' . $currencyCode . '.group_' . $groupId : '.' . $currencyCode . '.default';
        }

        return $this->priceKey;
    }

    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getStoreId();
    }

    public function getCurrentCategory()
    {
        return $this->registry->registry('current_category');
    }

    public function getAddToCartParams()
    {
        $url = $this->getAddToCartUrl();

        return [
            'action' => $url,
            'formKey' => $this->formKey->getFormKey(),
        ];
    }

    private function getAddToCartUrl($additional = [])
    {
        $continueUrl = $this->urlHelper->getEncodedUrl($this->_urlBuilder->getCurrentUrl());
        $urlParamName = ActionInterface::PARAM_NAME_URL_ENCODED;

        $routeParams = [
            $urlParamName => $continueUrl,
            '_secure' => $this->algoliaHelper->getRequest()->isSecure()
        ];

        if (!empty($additional)) {
            $routeParams = array_merge($routeParams, $additional);
        }

        return $this->_urlBuilder->getUrl('checkout/cart/add', $routeParams);
    }
}

