<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Data;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Registry\ReplicaState;
use Algolia\AlgoliaSearch\Service\Product\ReplicaManager;
use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class RebuildReplicasPatch implements DataPatchInterface
{
    public function __construct(
        protected ModuleDataSetupInterface $moduleDataSetup,
        protected StoreManagerInterface    $storeManager,
        protected ReplicaManager           $replicaManager,
        protected ProductHelper            $productHelper,
        protected AppState                 $appState,
        protected ReplicaState             $replicaState,
        protected ConfigHelper             $configHelper,
        protected LoggerInterface          $logger
    )
    {}

        /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [
            MigrateVirtualReplicaConfigPatch::class
        ];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function apply(): PatchInterface
    {
        if (!$this->configHelper->credentialsAreConfigured()) {
            $this->logger->warning("Algolia credentials are not configured. Aborting replica rebuild patch. If you need to rebuild your replicas run `bin/magento algolia:replicas:rebuild`");
            return $this;
        }

        $this->moduleDataSetup->getConnection()->startSetup();
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException) {
            // Area code is already set - nothing to do
        }

        $storeIds = array_filter(
            array_keys($this->storeManager->getStores()),
            function (int $storeId) { return $this->replicaManager->isReplicaSyncEnabled($storeId); }
        );

        try {
            // Delete all replicas before resyncing in case of incorrect replica assignments
            foreach ($storeIds as $storeId) {
                $this->retryDeleteReplica($storeId);
            }

            foreach ($storeIds as $storeId) {
                $this->replicaState->setChangeState(ReplicaState::REPLICA_STATE_CHANGED, $storeId); // avoids latency
                $this->replicaManager->syncReplicasToAlgolia($storeId, $this->productHelper->getIndexSettings($storeId));
            }
        }
        catch (AlgoliaException $e) {
            // Log the error but do not prevent setup:update
            $this->logger->error("Could not rebuild replicas - a full reindex may be required.");
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    protected function retryDeleteReplica(int $storeId, int $maxRetries = 3, int $interval = 5)
    {
        for ($tries = $maxRetries - 1; $tries >= 0; $tries--) {
            try {
                $this->replicaManager->deleteReplicasFromAlgolia($storeId);
                return;
            } catch (AlgoliaException $e) {
                $this->logger->warning(__("Unable to delete replicas, %1 tries remaining: %2", $tries, $e->getMessage()));
                sleep($interval);
            }
        }
        throw new ExceededRetriesException('Unable to delete old replica indices after $maxRetries retries.');
    }
}
