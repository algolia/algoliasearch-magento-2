<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Magento\Catalog\Model\Product\Action;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel;

class ProductObserver
{
    private $indexer;

    public function __construct(IndexerRegistry $indexerRegistry)
    {
        $this->indexer = $indexerRegistry->get('algolia_products');
    }

    public function aroundSave(
        \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \Closure $proceed,
        AbstractModel $product
    ) {
        $productResource->addCommitCallback(function () use ($product) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($product->getId());
            }
        });

        return $proceed($product);
    }

    public function aroundDelete(
        \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \Closure $proceed,
        AbstractModel $product
    ) {
        $productResource->addCommitCallback(function () use ($product) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($product->getId());
            }
        });

        return $proceed($product);
    }

    public function aroundUpdateAttributes(
        Action $subject,
        \Closure $closure,
        array $productIds,
        array $attrData,
        $storeId
    ) {
        $result = $closure($productIds, $attrData, $storeId);
        if (!$this->indexer->isScheduled()) {
            $this->indexer->reindexList(array_unique($productIds));
        }

        return $result;
    }

    public function aroundUpdateWebsites(
        Action $subject,
        \Closure $closure,
        array $productIds,
        array $websiteIds,
        $type
    ) {
        $result = $closure($productIds, $websiteIds, $type);
        if (!$this->indexer->isScheduled()) {
            $this->indexer->reindexList(array_unique($productIds));
        }

        return $result;
    }
}
