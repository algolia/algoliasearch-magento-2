<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\Product\Action;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Indexer\IndexerRegistry;

class ProductObserver
{
    /** @var Product */
    private $indexer;

    /** @var ProductResource */
    private $productResource;

    public function __construct(IndexerRegistry $indexerRegistry, ProductResource $productResource)
    {
        $this->indexer = $indexerRegistry->get('algolia_products');
        $this->productResource = $productResource;
    }

    /**
     * @param ProductResource $subject
     * @param ProductResource $result
     * @param ProductModel $product
     *
     * @return ProductResource
     */
    public function afterSave(ProductResource $subject, ProductResource $result, ProductModel $product) {
        $this->productResource->addCommitCallback(function () use ($product) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($product->getId());
            }
        });

        return $result;
    }

    /**
     * @param ProductResource $subject
     * @param ProductResource $result
     * @param ProductModel $product
     *
     * @return ProductResource
     */
    public function afterDelete(ProductResource $subject, ProductResource $result, ProductModel $product) {
        $this->productResource->addCommitCallback(function () use ($product) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($product->getId());
            }
        });

        return $result;
    }

    /**
     * @param Action $subject
     * @param Action $result
     * @param array $productIds
     *
     * @return Action
     */
    public function afterUpdateAttributes(Action $subject, Action $result, $productIds) {
        $this->productResource->addCommitCallback(function () use ($productIds) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexList(array_unique($productIds));
            }
        });

        return $result;
    }

    /**
     * @param Action $subject
     * @param Action $result
     * @param array $productIds
     *
     * @return mixed
     */
    public function afterUpdateWebsites(Action $subject, Action $result, array $productIds) {
        $this->productResource->addCommitCallback(function () use ($productIds) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexList(array_unique($productIds));
            }
        });

        return $result;
    }
}
