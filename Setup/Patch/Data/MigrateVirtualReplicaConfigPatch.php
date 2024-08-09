<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Data;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Exception\ReplicaLimitExceededException;
use Algolia\AlgoliaSearch\Exception\TooManyCustomerGroupsAsReplicasException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\ConfigChecker;
use Algolia\AlgoliaSearch\Service\Product\SortingTransformer;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Validator\VirtualReplicaValidatorFactory;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class MigrateVirtualReplicaConfigPatch implements DataPatchInterface
{
    public function __construct(
        protected ModuleDataSetupInterface       $moduleDataSetup,
        protected WriterInterface                $configWriter,
        protected ConfigInterface                $config,
        protected ReinitableConfigInterface      $scopeConfig,
        protected ConfigHelper                   $configHelper,
        protected ConfigChecker                  $configChecker,
        protected ReplicaManagerInterface        $replicaManager,
        protected SortingTransformer             $sortingTransformer,
        protected VirtualReplicaValidatorFactory $validatorFactory,
        protected StoreManagerInterface          $storeManager,
        protected StoreNameFetcher               $storeNameFetcher,
        protected SerializerInterface            $serializer
    )
    {}

    /**
     * @inheritDoc
     */
    public function apply(): PatchInterface
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $this->configChecker->checkAndApplyAllScopes(ConfigHelper::LEGACY_USE_VIRTUAL_REPLICA_ENABLED, [$this, 'migrateSetting']);

        $this->scopeConfig->reinit();

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * Seek each scoped global replica config and attempt to apply to the sorting config
     * Note that a scoping mismatch could occur so this patch will create a matching scoped sort
     * based on the scope of the original legacy virtual replica config
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function migrateSetting(string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeId = 0): void
    {
        // If not enabled - delete this old setting and move on...
        if (!$this->scopeConfig->isSetFlag(ConfigHelper::LEGACY_USE_VIRTUAL_REPLICA_ENABLED, $scope, $scopeId)) {
            $this->deleteLegacyConfig($scope, $scopeId);
            return;
        }

        // Get all stores affected by this configuration
        $storeIds = $this->configChecker->getAffectedStoreIds(
            ConfigHelper::LEGACY_USE_VIRTUAL_REPLICA_ENABLED,
            $scope,
            $scopeId
        );

        // Replicate the global settings by turning on virtual replicas for all attributes and initialize based on current scope
        $virtualizedSorts = $this->simulateFullVirtualReplicas($scope, $scopeId);

        // Retrieve the sorting config
        foreach ($storeIds as $storeId) {
            // Get the store specific sorting configuration
            $virtualizedSorts = $this->simulateFullVirtualReplicas(ScopeInterface::SCOPE_STORES, $storeId);

            $sortingIndices = $this->sortingTransformer->getSortingIndices($storeId, null, $virtualizedSorts);

            $validator = $this->validatorFactory->create();
            if (!$validator->isReplicaConfigurationValid($sortingIndices)) {
                $storeName = $this->storeNameFetcher->getStoreName($storeId) . " (Store ID=$storeId)";
                $prefix = "Error encountered while attempting to migrate your virtual replica configuration.\n";
                $postfix = "\nPlease note that there can be no more than " . $this->replicaManager->getMaxVirtualReplicasPerIndex() . " virtual replicas per index.";
                $postfix .= "\nYou can now configure virtual replicas by attribute under: Stores > Configuration > Algolia Search > InstantSearch Results Page > Sorting";
                $postfix .= "\nRun the \"bin/magento algolia:replicas:disable-virtual-replicas\" before running \"setup:upgrade\" and configure your virtual replicas in the Magento admin.";
                if ($validator->isTooManyCustomerGroups()) {
                    throw (new TooManyCustomerGroupsAsReplicasException(__("{$prefix}You have too many customer groups to enable virtual replicas on the pricing sort for $storeName.$postfix")))
                        ->withReplicaCount($validator->getReplicaCount())
                        ->withPriceSortReplicaCount($validator->getPriceSortReplicaCount());
                }
                else {
                    throw (new ReplicaLimitExceededException(__("{$prefix}Replica limit exceeded for $storeName.$postfix")))
                        ->withReplicaCount($validator->getReplicaCount());
                }
            }

            // If all is copacetic then save the new sorting config
            // Save to store scope if we are not already there and a store scope override exists
            if ($scope != ScopeInterface::SCOPE_STORES
                && $this->configChecker->isSettingAppliedForScopeAndCode(ConfigHelper::SORTING_INDICES, ScopeInterface::SCOPE_STORES, $storeId)) {
                $this->configHelper->setSorting($virtualizedSorts, ScopeInterface::SCOPE_STORES, $storeId);
            // If not overridden at store level next check for website overrides
            } else if ($scope != ScopeInterface::SCOPE_WEBSITES) {
                $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
                if ($this->configChecker->isSettingAppliedForScopeAndCode(ConfigHelper::SORTING_INDICES, ScopeInterface::SCOPE_WEBSITES, $websiteId)) {
                    $this->configHelper->setSorting($virtualizedSorts, ScopeInterface::SCOPE_WEBSITES, $websiteId);
                }
            }
        }

        // Save in the matching scope to the original legacy global config
        $this->configHelper->setSorting($virtualizedSorts, $scope, $scopeId);
        $this->deleteLegacyConfig($scope, $scopeId);
    }

    protected function deleteLegacyConfig($scope, $scopeId): void
    {
        $this->configWriter->delete(ConfigHelper::LEGACY_USE_VIRTUAL_REPLICA_ENABLED, $scope, $scopeId);
    }

    protected function simulateFullVirtualReplicas(string $scope, int $scopeId): array
    {
        $raw = $this->scopeConfig->getValue(ConfigHelper::SORTING_INDICES, $scope, $scopeId);
        return array_map(
            function($sort) {
                $sort[ReplicaManagerInterface::SORT_KEY_VIRTUAL_REPLICA] = 1;
                return $sort;
            },
            $this->serializer->unserialize($raw)
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
