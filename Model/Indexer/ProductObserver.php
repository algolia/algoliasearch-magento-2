<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Magento\Catalog\Model\Product\Action;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Indexer\IndexerRegistry;

class ProductObserver
{
    /** @var Product */
    private $indexer;

    public function __construct(IndexerRegistry $indexerRegistry)
    {
        $this->indexer = $indexerRegistry->get('algolia_products');
    }

    public function afterSave(ProductResource $productResource, ProductResource $result, ProductModel $product): ProductResource
    {
        $productResource->addCommitCallback(function () use ($product) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($product->getId());
            }
        });

        return $result;
    }

    public function afterDelete(ProductResource $productResource, ProductResource $result, ProductModel $product): ProductResource
    {
        $productResource->addCommitCallback(function () use ($product) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($product->getId());
            }
        });

        return $result;
    }

    public function afterUpdateAttributes(Action $subject, Action $result, array $productIds): Action
    {
        if (!$this->indexer->isScheduled()) {
            $this->indexer->reindexList(array_unique($productIds));
        }

        return $result;
    }

    public function afterUpdateWebsites(Action $subject, null $result, array $productIds): void
    {
        if (!$this->indexer->isScheduled()) {
            $this->indexer->reindexList(array_unique($productIds));
        }
    }
}
