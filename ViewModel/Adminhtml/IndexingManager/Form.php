<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml\IndexingManager;

use Algolia\AlgoliaSearch\ViewModel\Adminhtml\BackendView;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Store\Model\StoreManagerInterface;

class Form implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    public function __construct(
        protected BackendView $backendView,
        protected ConfigHelper $configHelper,
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
        return $this->getBackendView()->getUrlInterface()->getUrl('*/*/reindex');
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
     * @return string
     */
    public function getConfirmMessage(): string
    {
        $message = 'You\'re about to perform an entity full reindexing to Algolia.<br>';

        if (!$this->configHelper->isQueueActive()) {
            $message .= '<strong>Warning :</strong> Your Indexing Queue is not activated. Depending on the size of the data you want to index, it may takes a lot of time and resources.<br>';
            $message .= 'We highly suggest to turn it on if you\'re performing a full product reindexing with a large catalog.<br>';
        }

        $message .= 'Do you want to proceed ?';

        return $message;
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
