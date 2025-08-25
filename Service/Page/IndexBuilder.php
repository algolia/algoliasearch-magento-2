<?php

namespace Algolia\AlgoliaSearch\Service\Page;

use Algolia\AlgoliaSearch\Api\Builder\IndexBuilderInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\AbstractIndexBuilder;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\App\Emulation;

class IndexBuilder extends AbstractIndexBuilder implements IndexBuilderInterface
{
    public function __construct(
        protected ConfigHelper        $configHelper,
        protected DiagnosticsLogger   $logger,
        protected Emulation           $emulation,
        protected ScopeCodeResolver   $scopeCodeResolver,
        protected AlgoliaConnector    $algoliaConnector,
        protected IndexOptionsBuilder $indexOptionsBuilder,
        protected PageHelper          $pageHelper
    ){
        parent::__construct(
            $configHelper,
            $logger,
            $emulation,
            $scopeCodeResolver,
            $algoliaConnector
        );
    }

    /**
     * @param int $storeId
     * @param array|null $options
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     */
    public function buildIndexFull(int $storeId, ?array $options = null): void
    {
        $this->buildIndex($storeId, $options['entityIds'] ?? null, null);
    }

    /**
     * @param $storeId
     * @param array|null $entityIds
     * @param array|null $options
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     */
    public function buildIndex($storeId, ?array $entityIds, ?array $options): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        if (!$this->configHelper->isPagesIndexEnabled($storeId)) {
            $this->logger->log('Pages Indexing is not enabled for the store.');
            return;
        }

        $indexOptions = $this->indexOptionsBuilder->buildEntityIndexOptions($storeId);

        $this->startEmulation($storeId);

        $pages = $this->pageHelper->getPages($storeId, $entityIds);

        $this->stopEmulation();

        // if there are pageIds defined, do not index to _tmp
        $isFullReindex = (!$entityIds);

        if (isset($pages['toIndex']) && count($pages['toIndex'])) {
            $pagesToIndex = $pages['toIndex'];
            $toIndexOptions = $this->indexOptionsBuilder->buildEntityIndexOptions($storeId, $isFullReindex);

            foreach (array_chunk($pagesToIndex, 100) as $chunk) {
                try {
                    $this->saveObjects($chunk, $toIndexOptions);
                } catch (\Exception $e) {
                    $this->logger->log($e->getMessage());
                    continue;
                }
            }
        }

        if (!$isFullReindex && isset($pages['toRemove']) && count($pages['toRemove'])) {
            $pagesToRemove = $pages['toRemove'];
            foreach (array_chunk($pagesToRemove, 100) as $chunk) {
                try {
                    $this->algoliaConnector->deleteObjects($chunk, $indexOptions);
                } catch (\Exception $e) {
                    $this->logger->log($e->getMessage());
                    continue;
                }
            }
        }

        if ($isFullReindex) {
            $tempIndexOptions = $this->indexOptionsBuilder->buildEntityIndexOptions($storeId, true);

            $this->algoliaConnector->copyQueryRules($indexOptions, $tempIndexOptions);
            $this->algoliaConnector->moveIndex($tempIndexOptions, $indexOptions);
        }
        $this->algoliaConnector->setSettings(
            $indexOptions,
            $this->pageHelper->getIndexSettings($storeId)
        );
    }
}
