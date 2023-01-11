<?php

namespace Algolia\AlgoliaSearch\Observer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Module\Manager as ModuleManager;

class ReindexProductOnLastItemPurchaseIfMsiDisable implements ObserverInterface
{
    protected $indexer;

    /** @var ModuleManager  */
    protected $moduleManager;

    /** @var ProductRepositoryInterface  */
    protected $productRepository;

    /** @var IndexerRegistry  */
    protected $indexerRegistry;

    /**
     * @param ModuleManager $moduleManager
     * @param ProductRepositoryInterface $productRepository
     * @param IndexerRegistry $indexerRegistry
     */
    public function __construct(
        ModuleManager $moduleManager,
        ProductRepositoryInterface $productRepository,
        IndexerRegistry $indexerRegistry
    ) {
        $this->moduleManager = $moduleManager;
        $this->productRepository = $productRepository;
        $this->indexer = $indexerRegistry->get('algolia_products');
    }

    /**
     * @param Observer $observer
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        // Add product to Algolia Indexing Queue if last item was purchased and if Magento MSI related modules are disabled.
        if (!$this->moduleManager->isEnabled('Magento_Inventory')) {
            $quote = $observer->getEvent()->getQuote();
            $productIds = [];
            foreach ($quote->getAllItems() as $item) {
                $productIds[$item->getProductId()] = $item->getProductId();
                $children = $item->getChildrenItems();
                if ($children) {
                    foreach ($children as $childItem) {
                        $productIds[$childItem->getProductId()] = $childItem->getProductId();
                    }
                }
            }

            if ($productIds) {
                $productToReindex = [];
                foreach ($productIds as $productId) {
                    $product = $this->productRepository->getById($productId);
                    $stockInfo = $product->getData('quantity_and_stock_status');
                    if ($stockInfo['qty'] < 1) {
                        $productToReindex[] = $productId;
                    }
                }
                if (count($productToReindex) > 0) {
                    $this->indexer->reindexList($productToReindex);
                }

            }
        }
    }
}
