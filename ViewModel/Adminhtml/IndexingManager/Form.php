<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml\IndexingManager;

use Algolia\AlgoliaSearch\ViewModel\Adminhtml\BackendView;
use Magento\Store\Model\StoreManagerInterface;

class Form implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    public function __construct(
        protected BackendView $backendView,
        protected StoreManagerInterface $storeManager
    ) {}

    /**
     * @return BackendView
     */
    public function getBackendView()
    {
        return $this->backendView;
    }

    /**
     * @return string
     */
    public function getFormAction()
    {
        return $this->getBackendView()->getUrlInterface()->getUrl('*/*/reindex', ['_current' => true]);
    }

    /**
     *
     * @param $key
     *
     * @return string
     */
    public function getFormValue($key)
    {
        $formData = $this->getBackendView()->getBackendSession()->getAlgoliaAnalyticsFormData();

        return ($formData && isset($formData[$key])) ? $formData[$key] : '';
    }

    /**
     * @return array
     */
    public function getEntities(): array
    {
        return [
            'all' => 'All',
            'products' => 'Products',
            'categories' => 'Categories',
            'pages' => 'Pages'
        ];
    }

    /**
     * @return array
     */
    public function getStores(): array
    {
        $stores = [];
        $stores[0] = 'All';

        foreach ($this->storeManager->getStores() as $store) {
            $stores[$store->getId()] = $store->getName();
        }

        return $stores;
    }

}
