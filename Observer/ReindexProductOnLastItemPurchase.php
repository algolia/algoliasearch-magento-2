<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Observer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;

class ReindexProductOnLastItemPurchase implements ObserverInterface
{
    /** @var \Magento\Framework\Indexer\IndexerInterface */
    protected $indexer;

    /** @var ObjectManagerInterface */
    protected $objectManager;

    /** @var ModuleManager */
    protected $moduleManager;

    /** @var ProductRepositoryInterface */
    protected $productRepository;

    public function __construct(
        ObjectManagerInterface $objectManager,
        ModuleManager $moduleManager,
        ProductRepositoryInterface $productRepository,
        IndexerRegistry $indexerRegistry
    ) {
        $this->objectManager = $objectManager;
        $this->moduleManager = $moduleManager;
        $this->productRepository = $productRepository;
        $this->indexer = $indexerRegistry->get('algolia_products');
    }

    /**
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();
        if ($shipment->getOrigData('entity_id')) {
            return;
        }

        // Add product to Algolia Indexing Queue if last item was purchased and check if Magento MSI related modules are enabled.
        if ($this->moduleManager->isEnabled('Magento_Inventory')) {
            $isSingleMode = $this->objectManager->create(\Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface::class);
            $defaultSourceProvider = $this->objectManager->create(\Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface::class);

            if (!empty($shipment->getExtensionAttributes())
                && !empty($shipment->getExtensionAttributes()->getSourceCode())) {
                $sourceCode = $shipment->getExtensionAttributes()->getSourceCode();
            } elseif ($isSingleMode->execute()) {
                $sourceCode = $defaultSourceProvider->getCode();
            }

            foreach ($shipment->getAllItems() as $item) {
                $getSourceItemBySku = $this->objectManager->create(\Magento\InventoryApi\Api\GetSourceItemsBySkuInterface::class);
                $sourceItemList = $getSourceItemBySku->execute($item->getSku());
                foreach ($sourceItemList as $source) {
                    if ($source->getSourceCode() == $sourceCode) {
                        if ($source->getQuantity() < 1) {
                            $this->indexer->reindexRow($item->getProductId());
                        }
                    }
                }
            }
        }
    }
}
