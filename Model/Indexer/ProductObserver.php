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

    public function __construct(IndexerRegistry $indexerRegistry)
    {
        $this->indexer = $indexerRegistry->get('algolia_products');
    }

    /**
     * @param ProductResource $subject
     * @param ProductResource $result
     * @param ProductModel $product
     *
     * @return ProductResource
     */
    public function afterSave(ProductResource $productResource, ProductResource $result, ProductModel $product)
    {
        $productResource->addCommitCallback(function () use ($product) {
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
    public function afterDelete(ProductResource $productResource, ProductResource $result, ProductModel $product)
    {
        $productResource->addCommitCallback(function () use ($product) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($product->getId());
            }
        });

        return $result;
    }

    /**
     * @param Action $subject
     * @param Action|null $result
     * @param array $productIds
     *
     * @return Action
     */
    public function afterUpdateAttributes(Action $action, $result = null, $productIds)
    {
        if (!$this->indexer->isScheduled()) {
            $this->indexer->reindexList(array_unique($productIds));
        }

        return $result;
    }

    /**
     * @param Action $subject
     * @param null $result
     * @param array $productIds
     *
     * @return mixed
     */
    public function afterUpdateWebsites(Action $action, $result = null, array $productIds)
    {
        if (!$this->indexer->isScheduled()) {
            $this->indexer->reindexList(array_unique($productIds));
        }

        return $result;
    }
}
