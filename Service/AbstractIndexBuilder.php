<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\App\Emulation;

abstract class AbstractIndexBuilder
{
    protected bool $emulationRuns = false;

    public function __construct(
        protected ConfigHelper        $configHelper,
        protected DiagnosticsLogger   $logger,
        protected Emulation           $emulation,
        protected ScopeCodeResolver   $scopeCodeResolver,
        protected AlgoliaConnector    $algoliaConnector,
        protected IndexOptionsBuilder $indexOptionsBuilder
    ){}

    /**
     * @param $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    protected function isIndexingEnabled($storeId = null): bool
    {
        if ($this->configHelper->isEnabledBackend($storeId) === false) {
            $this->logger->log('INDEXING IS DISABLED FOR ' . $this->logger->getStoreName($storeId));
            return false;
        }
        return true;
    }

    /**
     * @param int $storeId
     * @return void
     * @throws \Exception
     */
    protected function startEmulation(int $storeId): void
    {
        if ($this->emulationRuns === true) {
            return;
        }

        $this->logger->start('START EMULATION');
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        $this->scopeCodeResolver->clean();
        $this->emulationRuns = true;
        $this->logger->stop('START EMULATION');
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function stopEmulation(): void
    {
        $this->logger->start('STOP EMULATION');
        $this->emulation->stopEnvironmentEmulation();
        $this->emulationRuns = false;
        $this->logger->stop('STOP EMULATION');
    }

    /**
     * @param array $objects
     * @param IndexOptionsInterface $indexOptions
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    protected function saveObjects(array $objects, IndexOptionsInterface $indexOptions): void
    {
        $this->algoliaConnector->saveObjects($indexOptions, $objects, $this->configHelper->isPartialUpdateEnabled());
    }

    /**
     * @param IndexOptionsInterface $indexOptions
     * @param array $idsToRemove
     * @param int|null $storeId
     * @return array
     * @throws AlgoliaException
     */
    protected function getIdsToRealRemove(IndexOptionsInterface $indexOptions, array $idsToRemove, ?int $storeId = null)
    {
        if (count($idsToRemove) === 1) {
            return $idsToRemove;
        }

        $toRealRemove = [];
        $idsToRemove = array_map('strval', $idsToRemove);
        foreach (array_chunk($idsToRemove, 1000) as $chunk) {
            $objects = $this->algoliaConnector->getObjects($indexOptions, $chunk);
            foreach ($objects['results'] as $object) {
                if (isset($object[AlgoliaConnector::ALGOLIA_API_OBJECT_ID])) {
                    $toRealRemove[] = $object[AlgoliaConnector::ALGOLIA_API_OBJECT_ID];
                }
            }
        }
        return $toRealRemove;
    }
}
