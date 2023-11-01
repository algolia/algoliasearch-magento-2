<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Api\CategoryVersionLoggerInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Logger;
use Algolia\AlgoliaSearch\Model\Indexer\Category as CategoryIndexer;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\AbstractModel;
use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Store\Model\StoreManagerInterface;


class CategoryObserver
{
    /** @var ResourceConnection */
    protected $resource;
    protected $logger;
    protected $storeManager;
    protected $categoryVersionLogger;
    /** @var IndexerRegistry */
    private $indexerRegistry;
    /** @var CategoryIndexer */
    private $indexer;
    /** @var ConfigHelper */
    private $configHelper;

    /**
     * CategoryObserver constructor.
     *
     * @param IndexerRegistry $indexerRegistry
     * @param ConfigHelper $configHelper
     * @param ResourceConnection $resource
     */
    public function __construct(
        IndexerRegistry                $indexerRegistry,
        ConfigHelper                   $configHelper,
        ResourceConnection             $resource,
        Logger                         $logger,
        StoreManagerInterface          $storeManager,
        CategoryVersionLoggerInterface $categoryVersionLogger
    )
    {
        $this->indexerRegistry = $indexerRegistry;
        $this->indexer = $indexerRegistry->get('algolia_categories');
        $this->configHelper = $configHelper;
        $this->resource = $resource;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->categoryVersionLogger = $categoryVersionLogger;
    }

    /**
     * @param CategoryResourceModel $categoryResource
     * @param CategoryResourceModel $result
     * @param CategoryModel $category
     *
     * @return CategoryResourceModel
     * @throws NoSuchEntityException
     */
    public function afterSave(
        CategoryResourceModel $categoryResource,
        CategoryResourceModel $result,
        CategoryModel         $category
    ) 
    {
        if (!$this->configHelper->getApplicationID()
            || !$this->configHelper->getAPIKey()
            || !$this->configHelper->getSearchOnlyAPIKey()) {
            return $result;
        }
        
        $storeId = $this->storeManager->getStore()->getId();

        $categoryResource->addCommitCallback(function () use ($category, $storeId) {
            $collectionIds = [];
            // To reduce the indexing operation for products, only update if these values have changed
            if ($this->isDataChanged($category, [
                CategoryInterface::KEY_NAME,
                CategoryInterface::KEY_PATH,
                CategoryInterface::KEY_INCLUDE_IN_MENU,
                CategoryInterface::KEY_IS_ACTIVE
            ])) {
                /** @var ProductCollection $productCollection */
                $productCollection = $category->getProductCollection();
                $collectionIds = (array) $productCollection->getColumnValues('entity_id');
                if ($this->isDataChanged($category, [CategoryInterface::KEY_PATH])) {
                    $this->categoryVersionLogger->logCategoryMove($category);
                } else {
                    $this->categoryVersionLogger->logCategoryChange($category, $storeId);
                }
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
     * @param AbstractModel $model
     * @param array $fields
     * @return bool
     */
    protected function isDataChanged(AbstractModel $model, array $fields): bool
    {
        foreach ($fields as $field) {
            if ($model->getOrigData($field) !== $model->getData($field)) {
                return true;
            }
        }
        return false;
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
        CategoryModel         $category
    )
    {
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
}
