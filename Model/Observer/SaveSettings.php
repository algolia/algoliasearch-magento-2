<?php

namespace Algolia\AlgoliaSearch\Model\Observer;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class SaveSettings implements ObserverInterface
{
    public const EVENTS = [
        'admin_system_config_changed_section_algoliasearch_instant'    => ['products'],
        'admin_system_config_changed_section_algoliasearch_images'     => ['products'],
        'admin_system_config_changed_section_algoliasearch_products'   => ['products'],
        'admin_system_config_changed_section_algoliasearch_categories' => ['categories']
    ];

    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected IndicesConfigurator $indicesConfigurator,
        protected Data $helper,
        protected ProductHelper $productHelper
    ){}

    /**
     * @param Observer $observer
     *
     * @throws AlgoliaException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $filteredEntities = self::EVENTS[$observer->getEvent()->getName()] ?? [];

         try {
            $storeIds = array_keys($this->storeManager->getStores());
            foreach ($storeIds as $storeId) {
                $this->indicesConfigurator->saveConfigurationToAlgolia($storeId, false, $filteredEntities);
            }
        } catch (\Exception $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }
}
