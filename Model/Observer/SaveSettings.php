<?php

namespace Algolia\AlgoliaSearch\Model\Observer;

use Algolia\AlgoliaSearch\Helper\Data;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;

class SaveSettings implements ObserverInterface
{
    protected $helper;
    protected $storeManager;

    public function __construct(StoreManagerInterface $storeManager, Data $helper)
    {
        $this->storeManager = $storeManager;
        $this->helper = $helper;
    }

    public function execute(Observer $observer)
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $this->helper->saveConfigurationToAlgolia($store->getId());
            }
        }
    }
}
