<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class Category implements Magento\Framework\Indexer\ActionInterface, Magento\Framework\Mview\ActionInterface
{
    private $storeManager;
    private $categoryHelper;
    private $fullAction;
    private $queue;
    private $configHelper;
    private $messageManager;
    private $output;

    public static $affectedProductIds = [];

    public function __construct(
        StoreManagerInterface $storeManager,
        CategoryHelper $categoryHelper,
        Data $helper,
        Queue $queue,
        ConfigHelper $configHelper,
        ManagerInterface $messageManager,
        ConsoleOutput $output
    ) {
        $this->fullAction = $helper;
        $this->storeManager = $storeManager;
        $this->categoryHelper = $categoryHelper;
        $this->queue = $queue;
        $this->configHelper = $configHelper;
        $this->messageManager = $messageManager;
        $this->output = $output;
    }

    public function execute($categoryIds)
    {
        if (!$this->configHelper->getApplicationID()
            || !$this->configHelper->getAPIKey()
            || !$this->configHelper->getSearchOnlyAPIKey()) {
            $errorMessage = 'Algolia reindexing failed: 
                You need to configure your Algolia credentials in Stores > Configuration > Algolia Search.';

            if (php_sapi_name() === 'cli') {
                $this->output->writeln($errorMessage);

                return;
            }

            $this->messageManager->addErrorMessage($errorMessage);

            return;
        }

        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            if ($this->fullAction->isIndexingEnabled($storeId) === false) {
                continue;
            }

            $this->rebuildAffectedProducts($storeId);

            $categoriesPerPage = $this->configHelper->getNumberOfElementByPage();

            if (is_array($categoryIds) && count($categoryIds) > 0) {
                $this->processSpecificCategories($categoryIds, $categoriesPerPage, $storeId);
                continue;
            }

            $this->processFullReindex($storeId, $categoriesPerPage);
        }
    }

    public function executeFull()
    {
        $this->execute(null);
    }

    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @param int $storeId
     */
    private function rebuildAffectedProducts($storeId)
    {
        $affectedProductsCount = count(self::$affectedProductIds);
        if ($affectedProductsCount > 0 && $this->configHelper->indexProductOnCategoryProductsUpdate($storeId)) {
            /** @uses Data::rebuildStoreProductIndex */
            $this->queue->addToQueue(
                Data::class,
                'rebuildStoreProductIndex',
                [
                    'store_id' => $storeId,
                    'product_ids' => self::$affectedProductIds,
                ],
                $affectedProductsCount
            );
        }
    }

    /**
     * @param array $categoryIds
     * @param int $categoriesPerPage
     * @param int $storeId
     */
    private function processSpecificCategories($categoryIds, $categoriesPerPage, $storeId)
    {
        foreach (array_chunk($categoryIds, $categoriesPerPage) as $chunk) {
            /** @uses Data::rebuildStoreCategoryIndex */
            $this->queue->addToQueue(
                Data::class,
                'rebuildStoreCategoryIndex',
                [
                    'store_id' => $storeId,
                    'category_ids' => $chunk,
                ],
                count($chunk)
            );
        }
    }

    /**
     * @param int $storeId
     * @param int $categoriesPerPage
     *
     * @throws Magento\Framework\Exception\LocalizedException
     * @throws Magento\Framework\Exception\NoSuchEntityException
     */
    private function processFullReindex($storeId, $categoriesPerPage)
    {
        /** @uses IndicesConfigurator::saveConfigurationToAlgolia */
        $this->queue->addToQueue(IndicesConfigurator::class, 'saveConfigurationToAlgolia', ['store_id' => $storeId]);

        $collection = $this->categoryHelper->getCategoryCollectionQuery($storeId);
        $size = $collection->getSize();

        $pages = ceil($size / $categoriesPerPage);

        for ($i = 1; $i <= $pages; $i++) {
            $data = [
                'store_id' => $storeId,
                'page' => $i,
                'page_size' => $categoriesPerPage,
            ];

            /** @uses Data::rebuildCategoryIndex */
            $this->queue->addToQueue(Data::class, 'rebuildCategoryIndex', $data, $categoriesPerPage);
        }
    }
}
