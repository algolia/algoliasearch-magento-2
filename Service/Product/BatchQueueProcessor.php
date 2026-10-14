<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Processor\BatchQueueProcessorInterface;
use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Model\Cache\Product\IndexCollectionSize;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobContextFactory;
use Algolia\AlgoliaSearch\Model\Data\Queue\JobDefinitionFactory;
use Algolia\AlgoliaSearch\Model\IndexMover;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Index\Settings\IndexSettingsComparator;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder as ProductIndexBuilder;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Exception\NoSuchEntityException;

class BatchQueueProcessor implements BatchQueueProcessorInterface
{
    public function __construct(
        protected Data                      $dataHelper,
        protected ConfigHelper              $configHelper,
        protected ProductHelper             $productHelper,
        protected QueueHelper               $queueHelper,
        protected Queue                     $queue,
        protected DiagnosticsLogger         $diag,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
        protected ProductIndexBuilder       $productIndexBuilder,
        protected IndexCollectionSize       $indexCollectionSizeCache,
        protected IndexOptionsBuilder       $indexOptionsBuilder,
        protected IndexSettingsComparator   $indexSettingsComparator,
        protected JobDefinitionFactory      $jobDefinitionFactory,
        protected JobContextFactory         $jobContextFactory
    ){}

    /**
     * @throws NoSuchEntityException
     * @throws DiagnosticsException
     */
    public function processBatch(int $storeId, ?array $entityIds = null): void
    {
        if (!$this->dataHelper->isIndexingEnabled($storeId)) {
            return;
        }

        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

            return;
        }

        $productsPerPage = $this->configHelper->getNumberOfElementByPage($storeId);

        if (!empty($entityIds)) {
            $this->handleDeltaIndex($entityIds, $storeId, $productsPerPage);

            return;
        }

        $useTmpIndex = $this->queueHelper->useTmpIndex($storeId);
        $this->syncAlgoliaSettings($storeId, $useTmpIndex);

        $this->handleFullIndex($storeId, $productsPerPage, $useTmpIndex);

        if ($useTmpIndex) {
            $this->moveTempIndex($storeId);
        }
    }

    /**
     * @throws DiagnosticsException
     */
    protected function getCollectionSize(int $storeId, Collection $collection): int
    {
        $this->diag->startProfiling(__METHOD__);
        $size = $this->indexCollectionSizeCache->get($storeId);
        if ($size === IndexCollectionSize::NOT_FOUND) {
            $size = $collection->getSize();
            $this->indexCollectionSizeCache->set($storeId, $size);
        }
        $this->diag->stopProfiling(__METHOD__);

        return $size;
    }

    protected function syncAlgoliaSettings(int $storeId, bool $useTmpIndex): void
    {
        $indexOptions = $this->indexOptionsBuilder->buildEntityIndexOptions($storeId, $useTmpIndex);
        $productSettings = $this->productHelper->getIndexSettings($storeId);

        // $useTmpIndex needs to be checked here because we need to ensure proper creation of the tmp index at this point
        // (tmp index needs to be initialized in the setSettings operation even if the settings are matching ALgolia dashboard)
        // => If no setSettings is performed before the first batch, this will result in the creation
        // of a tmp index without any settings, rules or synonyms.
        if (!$useTmpIndex && $this->indexSettingsComparator->matches($indexOptions, $productSettings)) {
            return;
        }

        /** @uses IndicesConfigurator::saveConfigurationToAlgolia() */
        $this->queue->addJob(
            $this->jobDefinitionFactory->create([
                'className' => IndicesConfigurator::class,
                'method'    => 'saveConfigurationToAlgolia',
                'storeId'   => $storeId,
                'data'      => [
                    'useTmpIndex' => $useTmpIndex,
                    'filteredEntities' => ['products'],
                ],
            ]),
            $this->jobContextFactory->create(['dataSize' => 1, 'isFullReindex' => true])
        );
    }

    /**
     * @throws NoSuchEntityException
     */
    protected function moveTempIndex(int $storeId): void {
        /** @uses IndexMover::moveIndexWithSetSettings() */
        $this->queue->addJob(
            $this->jobDefinitionFactory->create([
                'className' => IndexMover::class,
                'method'    => 'moveIndexWithSetSettings',
                'storeId'   => $storeId,
                'data'      => [
                    'tmpIndexName' => $this->productHelper->getTempIndexName($storeId),
                    'indexName'    => $this->productHelper->getIndexName($storeId),
                ],
            ]),
            $this->jobContextFactory->create(['dataSize' => 1, 'isFullReindex' => true])
        );
    }

    protected function handleDeltaIndex(array $entityIds, int $storeId, int $productsPerPage): void
    {
        $entityIds = array_unique(array_merge($entityIds, $this->productHelper->getParentProductIds($entityIds)));

        foreach (array_chunk($entityIds, $productsPerPage) as $i => $chunk) {
            /** @uses ProductIndexBuilder::buildIndexList() */
            $this->queue->addJob(
                $this->jobDefinitionFactory->create([
                    'className' => ProductIndexBuilder::class,
                    'method'    => 'buildIndexList',
                    'storeId'   => $storeId,
                    'data'      => [
                        'entityIds' => $chunk,
                        'options'   => [
                            'page'     => $i + 1,
                            'pageSize' => $productsPerPage,
                        ],
                    ],
                ]),
                $this->jobContextFactory->create(['dataSize' => count($chunk)])
            );
        }
    }

    /**
     * @throws DiagnosticsException
     */
    protected function handleFullIndex(int $storeId, int $productsPerPage, bool $useTmpIndex): void
    {
        $onlyVisible = !$this->configHelper->includeNonVisibleProductsInIndex();
        $collection = $this->productHelper->getProductCollectionQuery($storeId, [], $onlyVisible);
        $pages = ceil($this->getCollectionSize($storeId, $collection) / $productsPerPage);
        for ($i = 1; $i <= $pages; $i++) {
            /** @uses ProductIndexBuilder::buildIndexFull() */
            $this->queue->addJob(
                $this->jobDefinitionFactory->create([
                    'className' => ProductIndexBuilder::class,
                    'method'    => 'buildIndexFull',
                    'storeId'   => $storeId,
                    'data'      => [
                        'options' => [
                            'page'        => $i,
                            'pageSize'    => $productsPerPage,
                            'useTmpIndex' => $useTmpIndex,
                        ],
                    ],
                ]),
                $this->jobContextFactory->create(['dataSize' => $productsPerPage, 'isFullReindex' => true])
            );
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    public function deleteInactiveProducts(int $storeId): void
    {
        if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

            return;
        }

        $this->productIndexBuilder->deleteInactiveProducts($storeId);
    }
}
