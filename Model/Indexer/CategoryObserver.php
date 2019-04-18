<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Model\Indexer\Category as CategoryIndexer;
use Closure;
use Magento\Catalog\Model\Category as Category;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Indexer\IndexerRegistry;

class CategoryObserver
{
    private $indexer;

    public function __construct(IndexerRegistry $indexerRegistry)
    {
        $this->indexer = $indexerRegistry->get('algolia_categories');
    }

    /**
     * @param CategoryResourceModel $categoryResource
     * @param Closure $proceed
     * @param Category $category
     *
     * @return mixed
     */
    public function aroundSave(
        CategoryResourceModel $categoryResource,
        Closure $proceed,
        Category $category
    ) {
        $categoryResource->addCommitCallback(function () use ($category) {
            if (!$this->indexer->isScheduled()) {
                /** @var ProductCollection $productCollection */
                $productCollection = $category->getProductCollection();
                CategoryIndexer::$affectedProductIds = (array) $productCollection->getAllIds();

                $this->indexer->reindexRow($category->getId());
            }
        });

        return $proceed($category);
    }

    /**
     * @param CategoryResourceModel $categoryResource
     * @param Closure $proceed
     * @param Category $category
     *
     * @return mixed
     */
    public function aroundDelete(
        CategoryResourceModel $categoryResource,
        Closure $proceed,
        Category $category
    ) {
        $categoryResource->addCommitCallback(function () use ($category) {
            if (!$this->indexer->isScheduled()) {
                /** @var ProductCollection $productCollection */
                $productCollection = $category->getProductCollection();
                CategoryIndexer::$affectedProductIds = (array) $productCollection->getAllIds();

                $this->indexer->reindexRow($category->getId());
            }
        });

        return $proceed($category);
    }
}
