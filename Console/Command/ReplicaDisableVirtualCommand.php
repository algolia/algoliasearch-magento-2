<?php

namespace Algolia\AlgoliaSearch\Console\Command;

use Algolia\AlgoliaSearch\Api\Console\ReplicaSyncCommandInterface;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Console\Traits\ReplicaSyncCommandTrait;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\ConfigChecker;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReplicaDisableVirtualCommand extends AbstractReplicaCommand implements ReplicaSyncCommandInterface {
    use ReplicaSyncCommandTrait;

    public function __construct(
        protected WriterInterface           $configWriter,
        protected ConfigChecker             $configChecker,
        protected ReinitableConfigInterface $scopeConfig,
        protected SerializerInterface       $serializer,
        protected ConfigHelper              $configHelper,
        protected CacheManager              $cacheManager,
        protected ReplicaManagerInterface   $replicaManager,
        protected StoreManagerInterface     $storeManager,
        protected ProductHelper             $productHelper,
        State                               $state,
        StoreNameFetcher                    $storeNameFetcher,
        ?string                             $name = null
    )
    {
        parent::__construct($state, $storeNameFetcher, $name);
    }

    protected function getReplicaCommandName(): string
    {
        return 'disable-virtual-replicas';
    }

    protected function getCommandDescription(): string
    {
        return 'Disable virtual replicas for all product sorting attributes and revert to standard replicas';
    }

    protected function getStoreArgumentDescription(): string
    {
        return 'ID(s) for store(s) to disable virtual replicas (optional), if not specified disable virtual replicas for all stores';
    }

    protected function getAdditionalDefinition(): array
    {
        return [];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->input = $input;
        $this->setAreaCode();

        $storeIds = $this->getStoreIds($input);

        $output->writeln($this->decorateOperationAnnouncementMessage('Disabling virtual replicas for {{target}}', $storeIds));

        $okMsg = 'Configure virtual replicas by attribute under: Stores > Configuration > Algolia Search > InstantSearch Results Page > Sorting';
        if (!$this->confirmOperation($okMsg)) {
            return CLI::RETURN_SUCCESS;
        }

        $this->disableVirtualReplicas($storeIds);

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param int[] $storeIds
     * @return void
     */
    protected function disableVirtualReplicas(array $storeIds = []): void
    {
        $updates = [];
        if (count($storeIds)) {
            foreach ($storeIds as $storeId) {
                if ($this->disableVirtualReplicasForStore($storeId)) {
                    $updates[] = $storeId;
                }
            }
            if ($updates) {
                $this->scopeConfig->reinit();
                foreach ($updates as $storeId) {
                    $this->syncReplicasForStore($storeId);
                }
            }
        } else {
            $this->disableVirtualReplicasForAllStores();
        }
    }

    protected function disableVirtualReplicasForStore(int $storeId): bool
    {
        $storeName = $this->storeNameFetcher->getStoreName($storeId);
        $isStoreScoped = false;

        if ($this->configChecker->isSettingAppliedForScopeAndCode(
            ConfigHelper::LEGACY_USE_VIRTUAL_REPLICA_ENABLED,
            ScopeInterface::SCOPE_STORES,
            $storeId)
        ) {
            $isStoreScoped = true;
            $this->removeLegacyVirtualReplicaConfig(ScopeInterface::SCOPE_STORES, $storeId);
        }

        if ($this->configChecker->isSettingAppliedForScopeAndCode(
            ConfigHelper::SORTING_INDICES,
            ScopeInterface::SCOPE_STORES,
            $storeId)
        ) {
            $isStoreScoped = true;
            $this->disableVirtualReplicaSortConfig(ScopeInterface::SCOPE_STORES, $storeId);
        }

        if (!$isStoreScoped) {
            $this->output->writeln("<info>Virtual replicas are not configured at the store level for $storeName. You will need to re-run this command for all stores.</info>");
            return false;
        }

        return true;
    }

    protected function disableVirtualReplicasForAllStores(): void
    {
        $this->configChecker->checkAndApplyAllScopes(ConfigHelper::LEGACY_USE_VIRTUAL_REPLICA_ENABLED, [$this, 'removeLegacyVirtualReplicaConfig']);

        $this->configChecker->checkAndApplyAllScopes(ConfigHelper::SORTING_INDICES, [$this, 'disableVirtualReplicaSortConfig']);

        $this->scopeConfig->reinit();

        $this->syncReplicasForAllStores();
    }

    public function removeLegacyVirtualReplicaConfig(string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeId = 0): void
    {
        $value = $this->scopeConfig->getValue(ConfigHelper::LEGACY_USE_VIRTUAL_REPLICA_ENABLED, $scope, $scopeId);
        if (is_null($value)) {
            return;
        }
        $this->output->writeln("<info>Removing legacy configuration " . ConfigHelper::LEGACY_USE_VIRTUAL_REPLICA_ENABLED . " for $scope scope" . ($scope != ScopeConfigInterface::SCOPE_TYPE_DEFAULT ? " (ID=$scopeId)" : "") . "</info>");
        $this->configWriter->delete(ConfigHelper::LEGACY_USE_VIRTUAL_REPLICA_ENABLED, $scope, $scopeId);
    }

    public function disableVirtualReplicaSortConfig(string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeId = 0): void
    {
        $raw = $this->scopeConfig->getValue(ConfigHelper::SORTING_INDICES, $scope, $scopeId);
        if (!$raw) {
            return;
        }
        $sorting = array_map(
            function($sort) {
                $sort[ReplicaManagerInterface::SORT_KEY_VIRTUAL_REPLICA] = 0;
                return $sort;
            },
            $this->serializer->unserialize($raw)
        );
        $this->output->writeln("<info>Disabling all virtual replicas in " . ConfigHelper::SORTING_INDICES . " for $scope scope" . ($scope != ScopeConfigInterface::SCOPE_TYPE_DEFAULT ? " (ID=$scopeId)" : "") . "</info>");
        $this->configHelper->setSorting($sorting, $scope, $scopeId);
    }

}
