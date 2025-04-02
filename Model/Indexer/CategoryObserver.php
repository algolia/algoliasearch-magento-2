<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Model\Indexer\Category as CategoryIndexer;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Product\BatchQueueProcessor as ProductBatchQueueProcessor;
use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Store\Model\StoreManagerInterface;

class CategoryObserver
{
    /** @var CategoryIndexer */
    private $indexer;

    public function __construct(
        IndexerRegistry $indexerRegistry,
        protected StoreManagerInterface $storeManager,
        protected ResourceConnection $resource,
        protected ProductBatchQueueProcessor $productBatchQueueProcessor,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    ) {
        $this->indexer = $indexerRegistry->get('algolia_categories');
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
        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey()) {
            return $result;
        }
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
                $this->indexer->reindexRow($category->getId());
                $this->reindexAffectedProducts(array_unique(array_merge($changedProductIds, $collectionIds)));
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
                $this->indexer->reindexRow($category->getId());
                $this->reindexAffectedProducts(array_keys($productCollection));
            }
        });

        return $result;
    }

    /**
     * @param array $affectedProductIds
     * @return void
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    protected function reindexAffectedProducts(array $affectedProductIds): void
    {
        if (count($affectedProductIds) > 0) {
            foreach (array_keys($this->storeManager->getStores()) as $storeId) {
                $this->productBatchQueueProcessor->processBatch($storeId, $affectedProductIds);
            }
        }
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
