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
     *
     * @return string
     */
    public function getFormValue($key)
    {
        $formData = $this->getBackendView()->getBackendSession()->getAlgoliaAnalyticsFormData();

        return ($formData && isset($formData[$key])) ? $formData[$key] : '';
    }

    public function getEntities(): array
    {
        return [
            'all' => 'All',
            'products' => 'Products',
            'categories' => 'Categories',
            'pages' => 'Pages',
        ];
    }

    public function getConfirmMessage(): string
    {
        $message = 'You\'re about to perform an entity full reindexing to Algolia.\n';

        if (!$this->configHelper->isQueueActive()) {
            $message .= 'Warning : Your Indexing Queue is not activated. Depending on the size of the data you want to index, it may takes a lot of time and resources.\n';
            $message .= 'We highly suggest to turn it on if you\'re performing a full product reindexing with a large catalog.\n';
        }

        $message .= 'Do you want to proceed ?';

        return $message;
    }

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
