<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Exception\ReplicaLimitExceededException;
use Algolia\AlgoliaSearch\Exception\TooManyCustomerGroupsAsReplicasException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Model\IndexOptions;
use Algolia\AlgoliaSearch\Registry\ReplicaState;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Validator\VirtualReplicaValidatorFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * This class is responsible for managing the business logic related to translating the
 * Magento admin configuration to an Algolia replica configuration.
 * This involves:
 *  - Correctly identifying one or more impacted primary indices based on store scoping in Magento
 *  - Setting the replica configuration on the affected primary indices (whether standard or virtual)
 *  - Setting the associated replicas' ranking configuration
 *
 * To minimize the number of operations performed it also seeks to make as few changes as necessary
 * This is achieved by comparing the local Magento admin sorting configuration to Algolia's associated
 * primary index replica configuration prior to performing any updates which should involve
 * either add or delete operations.
 *
 * Lastly, to ensure compatibility with independent Algolia dashboard configuration which can include things
 * like "sorting strategies" that can be generated by Merchandising Studio this class only concerns itself
 * with replicas that is directly managed by Magento.
 *
 */
class ReplicaManager implements ReplicaManagerInterface
{
    public const ALGOLIA_SETTINGS_KEY_REPLICAS = 'replicas';

    protected const _DEBUG = true;

    // LOCAL CACHING VARIABLES
    /** @var array<string, string[]> */
    protected array $_algoliaReplicaConfig = [];

    /** @var array<int, string[]>  */
    protected array $_magentoReplicaPossibleConfig = [];

    /** @var array<int, string[]>  */
    protected array $_unusedReplicaIndices = [];

    public function __construct(
        protected ConfigHelper                   $configHelper,
        protected AlgoliaHelper                  $algoliaHelper,
        protected ReplicaState                   $replicaState,
        protected VirtualReplicaValidatorFactory $validatorFactory,
        protected IndexNameFetcher               $indexNameFetcher,
        protected StoreNameFetcher               $storeNameFetcher,
        protected SortingTransformer             $sortingTransformer,
        protected StoreManagerInterface          $storeManager,
        protected DiagnosticsLogger              $logger
    )
    {}

    /**
     * Evaluate the replica state of the index for a given store and determine
     * if Algolia and Magento are no longer in sync
     *
     * @return bool Returns true if replica state has changed or if unknown then result is determined based on whether Magento and Algolia have fallen out of sync
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function hasReplicaConfigurationChanged(int $storeId): bool
    {
        switch ($this->replicaState->getChangeState($storeId)) {
            case ReplicaState::REPLICA_STATE_CHANGED:
                return true;
            case ReplicaState::REPLICA_STATE_UNCHANGED:
                return false;
            case ReplicaState::REPLICA_STATE_UNKNOWN:
            default:
                $primaryIndexName = $this->indexNameFetcher->getProductIndexName($storeId);
                $old = $this->getMagentoReplicaConfigurationFromAlgolia($primaryIndexName, $storeId);
                $new = $this->sortingTransformer->transformSortingIndicesToReplicaSetting(
                    $this->sortingTransformer->getSortingIndices($storeId, null, null, true)
                );
                sort($old);
                sort($new);
                return $old !== $new;
        }
    }


    /**
     * @param $primaryIndexName
     * @param int|null $storeId
     * @param bool $refreshCache
     * @return string[]
     * @throws LocalizedException
     */
    protected function getReplicaConfigurationFromAlgolia($primaryIndexName, int $storeId = null, bool $refreshCache = false): array
    {
        if ($refreshCache || !isset($this->_algoliaReplicaConfig[$primaryIndexName])) {
            try {
                $currentSettings = $this->algoliaHelper->getSettings($primaryIndexName, $storeId);
                $this->_algoliaReplicaConfig[$primaryIndexName] = array_key_exists(self::ALGOLIA_SETTINGS_KEY_REPLICAS, $currentSettings)
                    ? $currentSettings[self::ALGOLIA_SETTINGS_KEY_REPLICAS]
                    : [];
            } catch (\Exception $e) {
                $msg = "Unable to retrieve replica settings for $primaryIndexName: " . $e->getMessage();
                $this->logger->error($msg);
                throw new LocalizedException(__($msg));
            }
        }
        return $this->_algoliaReplicaConfig[$primaryIndexName];
    }

    protected function clearAlgoliaReplicaSettingCache($primaryIndexName = null): void
    {
        if (is_null($primaryIndexName)) {
            $this->_algoliaReplicaConfig = [];
        } else {
            unset($this->_algoliaReplicaConfig[$primaryIndexName]);
        }
    }

    /**
     * Obtain the replica configuration from Algolia but only those indices that are
     * relevant to the Magento integration
     *
     * @param string $primaryIndexName
     * @param int|null $storeId
     * @param bool $refreshCache
     * @return string[] Array of replica index names
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getMagentoReplicaConfigurationFromAlgolia(
        string $primaryIndexName,
        int $storeId = null,
        bool $refreshCache = false
    ): array
    {
        $algoliaReplicas = $this->getReplicaConfigurationFromAlgolia($primaryIndexName, $storeId, $refreshCache);
        $magentoReplicas = $this->getMagentoReplicaSettings($primaryIndexName, $algoliaReplicas);
        return array_values(array_intersect($magentoReplicas, $algoliaReplicas));
    }

    /**
     * Filter out non Magento managed replicas
     * @param string $baseIndexName
     * @param string[] $algoliaReplicas
     * @return string[]
     * @throws NoSuchEntityException
     */
    protected function getMagentoReplicaSettings(string $baseIndexName, array $algoliaReplicas): array
    {
        return array_filter(
            $algoliaReplicas,
            function ($algoliaReplicaSetting) use ($baseIndexName) {
                return $this->isMagentoReplicaIndex($this->getBareIndexNameFromReplicaSetting($algoliaReplicaSetting), $baseIndexName);
            }
        );
    }

    /**
     * Perform logic to determine if this is a Magento managed replica index
     * (By default replicas will be considered Magento managed if they are prefixed with the primary index name)
     *
     * @param string $replicaIndexName
     * @param int|string $storeIdOrIndex
     * @return bool
     * @throws NoSuchEntityException
     */
    protected function isMagentoReplicaIndex(string $replicaIndexName, int|string $storeIdOrIndex): bool
    {
        $primaryIndexName = is_string($storeIdOrIndex) ? $storeIdOrIndex : $this->indexNameFetcher->getProductIndexName($storeIdOrIndex);
        return $replicaIndexName !== $primaryIndexName && str_starts_with($replicaIndexName, $primaryIndexName);
    }

    /**
     * @param string $primaryIndexName
     * @param int|null $storeId
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getNonMagentoReplicaConfigurationFromAlgolia(string $primaryIndexName, int $storeId = null): array
    {
        $algoliaReplicas = $this->getReplicaConfigurationFromAlgolia($primaryIndexName, $storeId);
        $magentoReplicas = $this->getMagentoReplicaSettings($primaryIndexName, $algoliaReplicas);
        return array_diff($algoliaReplicas, $magentoReplicas);
    }

    /**
     * In order to avoid interfering with replicas configured directly in the Algolia dashboard,
     * we must know which replica indices are Magento managed and which are not.
     * This method seeks to determine this based on Magento before/after state on a sorting config change
     * The downside here is that it will not work if Magento and Algolia get out of sync
     *
     * @param int $storeId
     * @param bool $refreshCache
     * @return string[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @deprecated This method has been supplanted by the much simpler getMagentoReplicaSettings() method
     */
    protected function getMagentoReplicaSettingsFromConfig(int $storeId, bool $refreshCache = false): array
    {
        if ($refreshCache || !isset($this->_magentoReplicaPossibleConfig[$storeId])) {
            $sortConfig = $this->replicaState->getChangeState($storeId) === ReplicaState::REPLICA_STATE_CHANGED
                ? array_merge($this->replicaState->getOriginalSortConfiguration($storeId), $this->replicaState->getUpdatedSortConfiguration($storeId))
                : null;
            $sortingIndices = $this->sortingTransformer->getSortingIndices($storeId, null, $sortConfig);
            $this->_magentoReplicaPossibleConfig[$storeId] = array_merge(
                $this->sortingTransformer->transformSortingIndicesToReplicaSetting($sortingIndices, SortingTransformer::REPLICA_TRANSFORM_MODE_STANDARD),
                $this->sortingTransformer->transformSortingIndicesToReplicaSetting($sortingIndices, SortingTransformer::REPLICA_TRANSFORM_MODE_VIRTUAL)
            );
        }
        return $this->_magentoReplicaPossibleConfig[$storeId];
    }

    /**
     * @inheritDoc
     */
    public function syncReplicasToAlgolia(int $storeId, array $primaryIndexSettings): void
    {
        if ($this->isReplicaSyncEnabled($storeId)
            && $this->hasReplicaConfigurationChanged($storeId)
            && $this->isReplicaConfigurationValid($storeId)) {
            $addedReplicas = $this->setReplicasOnPrimaryIndex($storeId);
            $this->configureRanking($storeId, $addedReplicas, $primaryIndexSettings);
        }
    }

    /**
     * @param int $storeId
     * @return string[] Replicas added or modified by this operation
     * @throws AlgoliaException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ExceededRetriesException
     */
    protected function setReplicasOnPrimaryIndex(int $storeId): array
    {
        $indexName = $this->indexNameFetcher->getProductIndexName($storeId);
        $sortingIndices = $this->sortingTransformer->getSortingIndices($storeId);
        $newMagentoReplicasSetting = $this->sortingTransformer->transformSortingIndicesToReplicaSetting($sortingIndices);
        $oldMagentoReplicasSetting = $this->getMagentoReplicaConfigurationFromAlgolia($indexName, $storeId, true);
        $nonMagentoReplicasSetting = $this->getNonMagentoReplicaConfigurationFromAlgolia($indexName, $storeId);
        $oldMagentoReplicaIndices = $this->getBareIndexNamesFromReplicaSetting($oldMagentoReplicasSetting);
        $newMagentoReplicaIndices = $this->getBareIndexNamesFromReplicaSetting($newMagentoReplicasSetting);

        $replicasToDelete = array_diff($oldMagentoReplicaIndices, $newMagentoReplicaIndices);
        $replicasToAdd = array_diff($newMagentoReplicaIndices, $oldMagentoReplicaIndices);
        $replicasToRank = $this->getBareIndexNamesFromReplicaSetting(array_diff($newMagentoReplicasSetting, $oldMagentoReplicasSetting));
        $replicasToUpdate = array_diff($replicasToRank, $replicasToAdd);

        $this->algoliaHelper->setSettings(
            $indexName,
            [self::ALGOLIA_SETTINGS_KEY_REPLICAS => array_merge($newMagentoReplicasSetting, $nonMagentoReplicasSetting)],
            false,
            false,
            '',
            $storeId
        );
        $setReplicasTaskId = $this->algoliaHelper->getLastTaskId($storeId);
        $this->algoliaHelper->waitLastTask($storeId, $indexName, $setReplicasTaskId);
        $this->clearAlgoliaReplicaSettingCache($indexName);
        $this->deleteIndices($replicasToDelete, false, $storeId);

        if (self::_DEBUG) {
            $this->logger->log(
                "Replicas configured on $indexName for store $storeId: "
                . count($replicasToAdd) . ' added, '
                . count($replicasToUpdate) . ' updated, '
                . count($replicasToDelete) . ' deleted'
            );
        }

        // include both added and updated replica indices
        return $replicasToRank;
    }

    /**
     * @param int $storeId
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ReplicaLimitExceededException
     * @throws TooManyCustomerGroupsAsReplicasException
     */
    protected function isReplicaConfigurationValid(int $storeId): bool
    {
        $sortingIndices = $this->sortingTransformer->getSortingIndices($storeId);
        $validator = $this->validatorFactory->create();
        if (!$validator->isReplicaConfigurationValid($sortingIndices)) {
            $storeName = $this->storeNameFetcher->getStoreName($storeId) . " (Store ID=$storeId)";
            $postfix = "Please note that there can be no more than " . $this->getMaxVirtualReplicasPerIndex() . " virtual replicas per index.";
            if ($this->revertReplicaConfig($storeId)) {
                $postfix .= ' Reverting to previous configuration.';
            }
            if ($validator->isTooManyCustomerGroups()) {
                throw (new TooManyCustomerGroupsAsReplicasException(__("You have too many customer groups to enable virtual replicas on the pricing sort for $storeName. $postfix")))
                    ->withReplicaCount($validator->getReplicaCount())
                    ->withPriceSortReplicaCount($validator->getPriceSortReplicaCount());
            }
            else {
                throw (new ReplicaLimitExceededException(__("Replica limit exceeded for $storeName. $postfix")))
                    ->withReplicaCount($validator->getReplicaCount());
            }
        }
        return true;
    }

    /**
     * In the event of an invalid replica configuration, this provides the means to revert the
     * configuration settings to the previous state (provided the ReplicaState has been utilized to track the change)
     * @param int $storeId
     * @return bool True if settings were reverted as a result of this function call
     */
    protected function revertReplicaConfig(int $storeId): bool
    {
        if ($ogConfig = $this->replicaState->getOriginalSortConfiguration($storeId)) {
            $this->configHelper->setSorting(
                $ogConfig,
                $this->replicaState->getParentScope(),
                $this->replicaState->getParentScopeId()
            );
            return true;
        }

        if ($this->replicaState->wereCustomerGroupsEnabled()) {
            $this->configHelper->setCustomerGroupsEnabled(
                false,
                $this->replicaState->getParentScope(),
                $this->replicaState->getParentScopeId());
            return true;
        }

        return false;
    }

    /**
     * @param string[] $replicas
     * @return string[]
     */
    protected function getBareIndexNamesFromReplicaSetting(array $replicas): array
    {
        return array_map(
            function ($str) {
                return $this->getBareIndexNameFromReplicaSetting($str);
            },
            $replicas
        );
    }

    protected function getBareIndexNameFromReplicaSetting(string $replicaSetting): string
    {
        return preg_replace('/.*\((.*)\).*/', '$1', $replicaSetting);
    }

    /**
     * @param array $replicasToDelete
     * @param bool $waitLastTask
     * @param null $storeId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     */
    protected function deleteIndices(array $replicasToDelete, bool $waitLastTask = false, $storeId = null): void
    {
        foreach ($replicasToDelete as $deletedReplica) {
            $indexOptions = new IndexOptions([
                IndexOptionsInterface::ENFORCED_INDEX_NAME => $deletedReplica,
                IndexOptionsInterface::STORE_ID => $storeId
            ]);
            $this->algoliaHelper->deleteIndex($indexOptions);
            if ($waitLastTask) {
                $this->algoliaHelper->waitLastTask($storeId, $deletedReplica);
            }
        }
    }

    /**
     * Apply ranking settings to the added replica indices
     * @param int $storeId
     * @param string[] $replicas
     * @param array<string, mixed> $primaryIndexSettings
     * @return void
     * @throws AlgoliaException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function configureRanking(int $storeId, array $replicas, array $primaryIndexSettings): void
    {
        $sortingIndices = $this->sortingTransformer->getSortingIndices($storeId);
        $replicaDetails = array_filter(
            $sortingIndices,
            function($replica) use ($replicas) {
                return in_array($replica['name'], $replicas);
            }
        );
        foreach ($replicaDetails as $replica) {
            $replicaName = $replica['name'];
            // Virtual replicas - relevant sort
            if (!empty($replica[self::SORT_KEY_VIRTUAL_REPLICA])) {
                $customRanking = array_key_exists('customRanking', $primaryIndexSettings)
                    ? $primaryIndexSettings['customRanking']
                    : [];
                array_unshift($customRanking, $replica['ranking'][0]);
                $this->algoliaHelper->setSettings(
                    $replicaName,
                    [ 'customRanking' => $customRanking ],
                    false,
                    false,
                    '',
                    $storeId
                );
            // Standard replicas - exhaustive sort
            } else {
                $primaryIndexSettings['ranking'] = $replica['ranking'];
                $this->algoliaHelper->setSettings(
                    $replicaName,
                    $primaryIndexSettings,
                    false,
                    false,
                    '',
                    $storeId
                );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function isReplicaSyncEnabled(int $storeId): bool
    {
        return $this->configHelper->isInstantEnabled($storeId);
    }

    /**
     * @inheritDoc
     */
    public function getMaxVirtualReplicasPerIndex() : int
    {
        return self::MAX_VIRTUAL_REPLICA_LIMIT;
    }

    /**
     * @throws AlgoliaException
     */
    protected function clearReplicasSettingInAlgolia(string $primaryIndexName, int $storeId): void
    {
        $this->algoliaHelper->setSettings(
            $primaryIndexName,
            [ self::ALGOLIA_SETTINGS_KEY_REPLICAS => []],
            false,
            false,
            '',
            $storeId
        )
        ;
        $this->algoliaHelper->waitLastTask($storeId, $primaryIndexName);
    }

    /**
     * @inheritDoc
     */
    public function deleteReplicasFromAlgolia(int $storeId, bool $unused = false): void
    {
        if ($unused) {
            $replicasToDelete = $this->getUnusedReplicaIndices($storeId);
        } else {
            $primaryIndexName = $this->indexNameFetcher->getProductIndexName($storeId);
            $replicasToDelete = $this->getMagentoReplicaIndicesFromAlgolia($primaryIndexName);
            $this->clearReplicasSettingInAlgolia($primaryIndexName, $storeId);
        }

        $this->deleteIndices($replicasToDelete);

        if ($unused) {
            $this->clearUnusedReplicaIndicesCache($storeId);
        }
    }

    /**
     * @throws LocalizedException
     */
    protected function getMagentoReplicaIndicesFromAlgolia(string $primaryIndexName, $storeId = null): array
    {
        return $this->getBareIndexNamesFromReplicaSetting($this->getMagentoReplicaConfigurationFromAlgolia($primaryIndexName, $storeId));
    }

    /**
     * @inheritDoc
     */
    public function getUnusedReplicaIndices(int $storeId): array
    {
        $primaryIndexName = $this->indexNameFetcher->getProductIndexName($storeId);
        if (!isset($this->_unusedReplicaIndices[$storeId])) {
            $currentReplicas = $this->getMagentoReplicaIndicesFromAlgolia($primaryIndexName);
            $unusedReplicas = [];
            $allIndices = $this->algoliaHelper->listIndexes($storeId);

            foreach ($allIndices['items'] as $indexInfo) {
                $indexName = $indexInfo['name'];
                if ($this->isMagentoReplicaIndex($indexName, $primaryIndexName)
                    && !$this->indexNameFetcher->isTempIndex($indexName)
                    && !$this->indexNameFetcher->isQuerySuggestionsIndex($indexName)
                    && !in_array($indexName, $currentReplicas))
                {
                    $unusedReplicas[] = $indexName;
                }
            }
            $this->_unusedReplicaIndices[$storeId] = $unusedReplicas;
        }


        return $this->_unusedReplicaIndices[$storeId];
    }

    protected function clearUnusedReplicaIndicesCache(?int $storeId = null): void
    {
        if (is_null($storeId)) {
            $this->_unusedReplicaIndices = [];
        } else {
            unset($this->_unusedReplicaIndices[$storeId]);
        }
    }

    /**
     * Get a list of all replica indices for all Magento managed stores
     * (This may be useful in case of cross store replica misconfiguration)
     * @return string[]
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function getAllReplicaIndices(): array
    {
        $replicaIndices = [];
        $storeIds = array_keys($this->storeManager->getStores());
        foreach ($storeIds as $storeId) {
            $primaryIndexName = $this->indexNameFetcher->getProductIndexName($storeId);
            $replicaIndices = array_merge(
                $replicaIndices,
                $this->getMagentoReplicaIndicesFromAlgolia($primaryIndexName)
            );
        }
        return array_unique($replicaIndices);
    }
}
