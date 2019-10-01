<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Model\Product\Action;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Indexer\IndexerRegistry;

class ProductObserver
{
    /** @var Product */
    private $indexer;

    /** @var ConfigHelper */
    private $configHelper;

    /**
     * @param IndexerRegistry $indexerRegistry
     * @param ConfigHelper $configHelper
     */
    public function __construct(IndexerRegistry $indexerRegistry, ConfigHelper $configHelper)
    {
        $this->indexer = $indexerRegistry->get('algolia_products');
        $this->configHelper = $configHelper;
    }

    /**
     * @param ProductResource $productResource
     * @param ProductResource $result
     * @param ProductModel $product
     *
     * @return ProductModel[]
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
     * @param ProductResource $productResource
     * @param ProductResource $result
     * @param ProductModel $product
     *
     * @return ProductModel[]
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
    public function afterUpdateAttributes(Action $subject, Action $result = null, $productIds)
    {
        if (!$this->indexer->isScheduled()) {
            $this->indexer->reindexList(array_unique($productIds));
        }

        return $result;
    }

    /**
     * @param Action $subject
     * @param Action|null $result
     * @param array $productIds
     *
     * @return mixed
     */
    public function afterUpdateWebsites(Action $subject, Action $result = null, array $productIds)
    {
        if (!$this->indexer->isScheduled()) {
            $this->indexer->reindexList(array_unique($productIds));
        }

        return $result;
    }
}
