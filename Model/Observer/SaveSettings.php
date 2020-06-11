<?php

namespace Algolia\AlgoliaSearch\Model\Observer;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Model\ConfigurationTracker;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;

class SaveSettings implements ObserverInterface
{
    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var IndicesConfigurator */
    private $indicesConfigurator;

    /** @var ConfigurationTracker */
    private $configurationTracker;

    public function __construct(
        StoreManagerInterface $storeManager,
        IndicesConfigurator $indicesConfigurator,
        ConfigurationTracker $configurationTracker
    ) {
        $this->storeManager = $storeManager;
        $this->indicesConfigurator = $indicesConfigurator;
        $this->configurationTracker = $configurationTracker;
    }

    /**
     * @param Observer $observer
     *
     * @throws AlgoliaException
     */
    public function execute(Observer $observer)
    {
        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            $this->indicesConfigurator->saveConfigurationToAlgolia($storeId);
            $this->configurationTracker->trackConfiguration($storeId);
        }
    }
}
