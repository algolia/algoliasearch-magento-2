<?php

namespace Algolia\AlgoliaSearch\Model\Observer;

use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use AlgoliaSearch\AlgoliaException;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;

class SaveSettings implements ObserverInterface
{
    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var IndicesConfigurator */
    private $indicesConfigurator;

    public function __construct(StoreManagerInterface $storeManager, IndicesConfigurator $indicesConfigurator)
    {
        $this->storeManager = $storeManager;
        $this->indicesConfigurator = $indicesConfigurator;
    }

    /**
     * @param Observer $observer
     *
     * @throws AlgoliaException
     */
    public function execute(Observer $observer)
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $this->indicesConfigurator->saveConfigurationToAlgolia($store->getId());
            }
        }
    }
}
