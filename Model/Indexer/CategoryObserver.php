<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel;

class CategoryObserver
{
    private $indexer;
    private $productsOrderIndex;

    public function __construct(IndexerRegistry $indexerRegistry)
    {
        $this->indexer = $indexerRegistry->get('algolia_categories');
        $this->productsOrderIndex = $indexerRegistry->get('algolia_category_products_order');
    }

    public function aroundSave(
        \Magento\Catalog\Model\ResourceModel\Category $categoryResource,
        \Closure $proceed,
        AbstractModel $category
    ) {
        $categoryResource->addCommitCallback(function () use ($category) {
            if (!$this->indexer->isScheduled()) {
                Category::$affectedProductIds = (array) $category->getData('affected_product_ids');
                $this->indexer->reindexRow($category->getId());

                $this->productsOrderIndex->reindexRow($category->getId());
            }
        });

        return $proceed($category);
    }

    public function aroundDelete(
        \Magento\Catalog\Model\ResourceModel\Category $categoryResource,
        \Closure $proceed,
        AbstractModel $category
    ) {
        $categoryResource->addCommitCallback(function () use ($category) {
            if (!$this->indexer->isScheduled()) {
                Category::$affectedProductIds = (array) $category->getData('affected_product_ids');
                $this->indexer->reindexRow($category->getId());
                $this->productsOrderIndex->reindexRow($category->getId());
            }
        });

        return $proceed($category);
    }
}
