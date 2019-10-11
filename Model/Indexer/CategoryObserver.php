<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Indexer\Category as CategoryIndexer;
use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Indexer\IndexerRegistry;

class CategoryObserver
{
    /** @var IndexerRegistry */
    private $indexerRegistry;

    /** @var CategoryIndexer */
    private $indexer;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var ResourceConnection */
    protected $resource;

    /**
     * CategoryObserver constructor.
     *
     * @param IndexerRegistry $indexerRegistry
     * @param ConfigHelper $configHelper
     * @param ResourceConnection $resource
     */
    public function __construct(
        IndexerRegistry $indexerRegistry,
        ConfigHelper $configHelper,
        ResourceConnection $resource
    ) {
        $this->indexerRegistry = $indexerRegistry;
        $this->indexer = $indexerRegistry->get('algolia_categories');
        $this->configHelper = $configHelper;
        $this->resource = $resource;
    }

    /**
     * @param CategoryResourceModel $categoryResource
     * @param CategoryResourceModel $result
     * @param CategoryModel $category
     *
     * @return CategoryResourceModel
     */
    public function afterSave(
        CategoryResourceModel $categoryResource,
        CategoryResourceModel $result,
        CategoryModel $category
    ) {
        $categoryResource->addCommitCallback(function () use ($category) {
            $collectionIds = [];
            // To reduce the indexing operation for products, only update if these values have changed
            if ($category->getOrigData('name') !== $category->getData('name')
                || $category->getOrigData('include_in_menu') !== $category->getData('include_in_menu')
                || $category->getOrigData('is_active') !== $category->getData('is_active')
                || $category->getOrigData('path') !== $category->getData('path')) {
                /** @var ProductCollection $productCollection */
                $productCollection = $category->getProductCollection();
                $collectionIds = (array) $productCollection->getColumnValues('entity_id');
            }
            $changedProductIds = ($category->getChangedProductIds() !== null ? (array) $category->getChangedProductIds() : []);

            if (!$this->indexer->isScheduled()) {
                CategoryIndexer::$affectedProductIds = array_unique(array_merge($changedProductIds, $collectionIds));
                $this->indexer->reindexRow($category->getId());
            } else {
                // missing logic, if scheduled, when category is saved w/out product, products need to be added to _cl
                if (count($changedProductIds) === 0 && count($collectionIds) > 0) {
                    $this->updateCategoryProducts($collectionIds);
                }
            }
        });

        return $result;
    }

    /**
     * @param CategoryResourceModel $categoryResource
     * @param CategoryResourceModel $result
     * @param CategoryModel $category
     *
     * @return CategoryResourceModel
     */
    public function afterDelete(
        CategoryResourceModel $categoryResource,
        CategoryResourceModel $result,
        CategoryModel $category
    ) {
        $categoryResource->addCommitCallback(function () use ($category) {
            // mview should be able to handle the changes for catalog_category_product relationship
            if (!$this->indexer->isScheduled()) {
                /* we are using products position because getProductCollection() doesn't use correct store */
                $productCollection = $category->getProductsPosition();
                CategoryIndexer::$affectedProductIds = array_keys($productCollection);

                $this->indexer->reindexRow($category->getId());
            }
        });

        return $result;
    }

    /**
     * @param array $productIds
     */
    private function updateCategoryProducts(array $productIds)
    {
        $productIndexer = $this->indexerRegistry->get('algolia_products');
        if (!$productIndexer->isScheduled()) {
            // if the product index is not schedule, it should still index these products
            $productIndexer->reindexList($productIds);
        } else {
            $view = $productIndexer->getView();
            $changelogTableName = $this->resource->getTableName($view->getChangelog()->getName());
            $connection = $this->resource->getConnection();
            if ($connection->isTableExists($changelogTableName)) {
                $data = [];
                foreach ($productIds as $productId) {
                    $data[] = ['entity_id' => $productId];
                }
                $connection->insertMultiple($changelogTableName, $data);
            }
        }
    }
}
