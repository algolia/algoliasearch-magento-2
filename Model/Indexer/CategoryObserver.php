<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Model\Indexer\Category as CategoryIndexer;
use Magento\Catalog\Model\Category as Category;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Framework\Indexer\IndexerRegistry;

class CategoryObserver
{
    private $indexer;

    public function __construct(IndexerRegistry $indexerRegistry)
    {
        $this->indexer = $indexerRegistry->get('algolia_categories');
    }

    public function afterSave(
        CategoryResourceModel $categoryResource,
        $result,
        Category $category
    ) {
        if (!$this->indexer->isScheduled()) {
            /** @var Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection */
            $productCollection = $category->getProductCollection();
            CategoryIndexer::$affectedProductIds = (array) $productCollection->getColumnValues('entity_id');

            $this->indexer->reindexRow($category->getId());
        }
    }

    public function beforeDelete(
        CategoryResourceModel $categoryResource,
        Category $category
    ) {
        if (!$this->indexer->isScheduled()) {
            /* we are using products position because getProductCollection() does use correct store */
            $productCollection = $category->getProductsPosition();
            CategoryIndexer::$affectedProductIds = array_keys($productCollection);

            $this->indexer->reindexRow($category->getId());
        }
    }
}
