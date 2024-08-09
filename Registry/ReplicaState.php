<?php

namespace Algolia\AlgoliaSearch\Registry;

class ReplicaState
{
    public const REPLICA_STATE_UNCHANGED = 1;
    public const REPLICA_STATE_CHANGED = 2;
    public const REPLICA_STATE_UNKNOWN = 3;

    private array $_scopeState = [];

    private array $_scopeConfigurationOld = [];

    private array $_scopeConfigurationNew = [];

    private ?string $_parentScope = null;
    private ?int $_parentScopeId = null;

    private bool $_customerGroupsEnabled = false;

    /**
     * Save context of the original admin operation
     * This is considered the "parent" scope.
     * This is different from the affected store IDs for the applied/parent scope
     * Retaining this is necessary for potential state reversion on error because while changes are persisted
     * to Algolia indices for each store, the idea of default / website scope is a Magento only concept.
     *
     * @param string $scope
     * @param int $scopeId
     * @return void
     */
    public function setAppliedScope(string $scope, int $scopeId): void {
        $this->_parentScope = $scope;
        $this->_parentScopeId = $scopeId;
    }

    public function getParentScope(): ?string
    {
        return $this->_parentScope;
    }

    public function getParentScopeId(): ?int
    {
        return $this->_parentScopeId;
    }

    public function getOriginalSortConfiguration(int $storeId): array
    {
        return $this->_scopeConfigurationOld[$storeId] ?? [];
    }

    public function setOriginalSortConfiguration(array $originalSortConfiguration, int $storeId): void
    {
        $this->_scopeConfigurationOld[$storeId] = $originalSortConfiguration;
    }

    public function getUpdatedSortConfiguration(int $storeId): array
    {
        return $this->_scopeConfigurationNew[$storeId] ?? [];
    }

    public function setUpdatedSortConfiguration(array $updatedSortConfiguration, int $storeId): void
    {
        $this->_scopeConfigurationNew[$storeId] = $updatedSortConfiguration;
    }

    protected function hasConfigDataToCompare(int $storeId): bool
    {
        return isset($this->_scopeConfigurationOld[$storeId])
            && isset($this->_scopeConfigurationNew[$storeId]);
    }

    public function getChangeState(int $storeId): int
    {
        $state = $this->_scopeState[$storeId] ?? self::REPLICA_STATE_UNKNOWN;
        if ($state === self::REPLICA_STATE_UNKNOWN && $this->hasConfigDataToCompare($storeId)) {
            $state = $this->getOriginalSortConfiguration($storeId) === $this->getUpdatedSortConfiguration($storeId)
                ? self::REPLICA_STATE_UNCHANGED
                : self::REPLICA_STATE_CHANGED;
        }
        return $state;
    }

    public function setChangeState(int $state, int $storeId): void
    {
        if ($state < self::REPLICA_STATE_UNCHANGED || $state > self::REPLICA_STATE_UNKNOWN) {
            $state = self::REPLICA_STATE_UNKNOWN;
        }
        $this->_scopeState[$storeId] = $state;
    }

    public function wereCustomerGroupsEnabled(): bool
    {
        return $this->_customerGroupsEnabled;
    }

    public function setCustomerGroupsEnabled(bool $customerGroupsEnabled): void
    {
        $this->_customerGroupsEnabled = $customerGroupsEnabled;
    }

}
