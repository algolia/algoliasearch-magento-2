<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Indexer\Category as CategoryIndexer;
use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Indexer\IndexerRegistry;

class CategoryObserver
{
    /** @var CategoryIndexer */
    private $indexer;

    /** @var ConfigHelper */
    private $configHelper;

    /**
     * @param IndexerRegistry $indexerRegistry
     * @param ConfigHelper $configHelper
     */
    public function __construct(IndexerRegistry $indexerRegistry, ConfigHelper $configHelper)
    {
        $this->indexer = $indexerRegistry->get('algolia_categories');
        $this->configHelper = $configHelper;
    }

    /**
     * Using "before" method here instead of "after", because M2.1 doesn't pass "$product" argument
     * to "after" methods. When M2.1 support will be removed, this method can be rewriten to:
     * afterSave(CategoryResourceModel $categoryResource, CategoryResourceModel $result, CategoryModel $category)
     *
     * @param CategoryResourceModel $categoryResource
     * @param CategoryModel $category
     *
     * @return CategoryModel[]
     */
    public function beforeSave(CategoryResourceModel $categoryResource, CategoryModel $category)
    {
        $categoryResource->addCommitCallback(function() use ($category) {
            if (!$this->indexer->isScheduled() || $this->configHelper->isQueueActive()) {
                /** @var ProductCollection $productCollection */
                $productCollection = $category->getProductCollection();
                CategoryIndexer::$affectedProductIds = (array) $productCollection->getColumnValues('entity_id');

                $this->indexer->reindexRow($category->getId());
            }
        });

        return [$category];
    }

    /**
     * @param CategoryResourceModel $categoryResource
     * @param CategoryModel $category
     *
     * @return CategoryModel[]
     */
    public function beforeDelete(CategoryResourceModel $categoryResource, CategoryModel $category)
    {
        $categoryResource->addCommitCallback(function() use ($category) {
            if (!$this->indexer->isScheduled() || $this->configHelper->isQueueActive()) {
                /* we are using products position because getProductCollection() doesn't use correct store */
                $productCollection = $category->getProductsPosition();
                CategoryIndexer::$affectedProductIds = array_keys($productCollection);

                $this->indexer->reindexRow($category->getId());
            }
        });

        return [$category];
    }
}
