<?php

namespace Algolia\AlgoliaSearch\Model\Observer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\IndexerRegistry;

class CategoryMoveAfter implements ObserverInterface
{

    /** @var IndexerRegistry */
    private $indexerRegistry;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var ResourceConnection */
    protected $resource;

    /**
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
        $this->configHelper = $configHelper;
        $this->resource = $resource;
    }

    /**
     * Category::move() does not run save so the plugin observing the save method
     * is not able to process the products that need updating.
     *
     * @param Observer $observer
     * @return bool|void
     */
    public function execute(Observer $observer)
    {
        /** @var Category $category */
        $category = $observer->getEvent()->getCategory();
        if (!$this->configHelper->indexProductOnCategoryProductsUpdate($category->getStoreId())) {
            return false;
        }

        $productIndexer = $this->indexerRegistry->get('algolia_products');
        if ($category->getOrigData('path') !== $category->getData('path')) {
            $productIds = array_keys($category->getProductsPosition());

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
}
