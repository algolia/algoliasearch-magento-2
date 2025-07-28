<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Data;

use Magento\Framework\Indexer\IndexerInterfaceFactory;
use Magento\Framework\Mview\View\SubscriptionFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class UpdateMviewPatch implements DataPatchInterface
{
    /**
     * @var SubscriptionFactory
     */
    protected $subscriptionFactory;

    /**
     * @var IndexerInterfaceFactory
     */
    protected $indexerFactory;

    /**
     * @var ModuleDataSetupInterface
     */
    protected $moduleDataSetup;

    /**
     * @param SubscriptionFactory $subscriptionFactory
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param IndexerInterfaceFactory $indexerFactory
     */
    public function __construct(
        SubscriptionFactory $subscriptionFactory,
        ModuleDataSetupInterface $moduleDataSetup,
        IndexerInterfaceFactory $indexerFactory
    ) {
        $this->subscriptionFactory = $subscriptionFactory;
        $this->moduleDataSetup = $moduleDataSetup;
        $this->indexerFactory = $indexerFactory;
    }

    /**
     * @return UpdateMviewPatch|void
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        try {
            /** @var \Magento\Framework\Indexer\IndexerInterface $indexer */
            $indexer = $this->indexerFactory->create()->load('algolia_products');
            $subscriptionInstance = $this->subscriptionFactory->create(
                [
                    'view' => $indexer->getView(),
                    'tableName' => $this->moduleDataSetup->getTable('catalog_product_index_price'),
                    'columnName' => 'entity_id',
                ]
            );
            $subscriptionInstance->remove();
        } catch (\Exception) {
            // Skip
        }
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
