<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Indexer\IndexerInterface;

class MissingPriceIndexHandler
{
    protected IndexerInterface $indexer;
    public function __construct(
        protected CollectionFactory $productCollectionFactory,
        IndexerRegistry $indexerRegistry
    )
    {
        $this->indexer = $indexerRegistry->get('catalog_product_price');
    }

    /**
     * @param array $productIds
     * @return int[] Array of product IDs that were reindexed by this repair operation
     */
    public function refreshPriceIndex(array $productIds): array
    {
        $reindexIds = $this->getProductIdsToReindex($productIds);
        if (empty($reindexIds)) {
            return [];
        }

        $this->indexer->reindexList($reindexIds);

        return $reindexIds;
    }

    /**
     * @param int[] $productIds
     * @return int[]
     */
    protected function getProductIdsToReindex(array $productIds): array
    {
        $state = $this->indexer->getState()->getStatus();
        if ($state === \Magento\Framework\Indexer\StateInterface::STATUS_INVALID) {
            return $productIds;
        }

        $collection = $this->productCollectionFactory->create();

        $collection->addAttributeToSelect(['name', 'price']);

        $collection->getSelect()->joinLeft(
            ['price_index' => 'catalog_product_index_price'],
            'e.entity_id = price_index.entity_id',
            []
        );

        $collection->getSelect()
            ->where('price_index.entity_id IS NULL')
            ->where('entity_id IN (?)', $productIds);

        return $collection->getAllIds();
    }
}
