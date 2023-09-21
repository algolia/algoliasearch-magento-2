<?php

namespace Algolia\AlgoliaSearch\Model\Observer;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;

class SaveSettings implements ObserverInterface
{
    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var IndicesConfigurator */
    protected $indicesConfigurator;

    /**
     * @var AlgoliaHelper
     */
    protected $algoliaHelper;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var ProductHelper
     */
    protected $productHelper;

    /**
     * @param StoreManagerInterface $storeManager
     * @param IndicesConfigurator $indicesConfigurator
     * @param AlgoliaHelper $algoliaHelper
     * @param Data $helper
     * @param ProductHelper $productHelper
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        IndicesConfigurator $indicesConfigurator,
        AlgoliaHelper $algoliaHelper,
        Data $helper,
        ProductHelper $productHelper
    ) {
        $this->storeManager = $storeManager;
        $this->indicesConfigurator = $indicesConfigurator;
        $this->algoliaHelper = $algoliaHelper;
        $this->helper = $helper;
        $this->productHelper = $productHelper;
    }

    /**
     * @param Observer $observer
     *
     * @throws AlgoliaException
     */
    public function execute(Observer $observer)
    {
        try {
            $storeIds = array_keys($this->storeManager->getStores());
            foreach ($storeIds as $storeId) {
                $indexName = $this->helper->getIndexName($this->productHelper->getIndexNameSuffix(), $storeId);
                $currentSettings = $this->algoliaHelper->getSettings($indexName);
                if (is_array($currentSettings) && array_key_exists('replicas', $currentSettings)) {
                    $this->algoliaHelper->setSettings($indexName, ['replicas' => []]);
                    $setReplicasTaskId = $this->algoliaHelper->getLastTaskId();
                    $this->algoliaHelper->waitLastTask($indexName, $setReplicasTaskId);
                    if (count($currentSettings['replicas']) > 0) {
                        foreach ($currentSettings['replicas'] as $replicaIndex) {
                            $this->algoliaHelper->deleteIndex($replicaIndex);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
        foreach ($storeIds as $storeId) {
            $this->indicesConfigurator->saveConfigurationToAlgolia($storeId);
        }
    }
}
