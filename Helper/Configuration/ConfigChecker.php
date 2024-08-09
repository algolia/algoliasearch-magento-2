<?php

namespace Algolia\AlgoliaSearch\Helper\Configuration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConfigChecker
{
    public function __construct(
        protected ScopeConfigInterface       $scopeConfig,
        protected StoreManagerInterface      $storeManager,
        protected WebsiteRepositoryInterface $websiteRepository
    ) {}

    /**
     * Is a scoped value different from its higher scope?
     * @param string $path
     * @param string $scope
     * @param mixed $code
     * @return bool
     */
    public function isSettingAppliedForScopeAndCode(string $path, string $scope, mixed $code): bool
    {
        $value = $this->scopeConfig->getValue($path, $scope, $code);
        if ($scope === ScopeInterface::SCOPE_STORES) {
            $defaultValue = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITES, $this->storeManager->getStore($code)->getWebsiteId());
        } else {
            $defaultValue = $this->scopeConfig->getValue($path);
        }
        return ($value !== $defaultValue);
    }

    /**
     * Does a store config override the website config?
     * @param string $path
     * @param mixed $websiteId
     * @param int $storeId
     * @return bool
     */
    protected function isStoreSettingOverridingWebsite(string $path, mixed $websiteId, int $storeId): bool
    {
        $storeValue = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORES, $storeId);
        $websiteValue = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITES, $websiteId);
        return ($storeValue !== $websiteValue);
    }

    /**
     * For a given path, check if that path has a non-default value
     * and if so perform corresponding logic (specified via callback)
     *
     * @param string $path
     * @param callable $callback Callback to execute for a given config scope
     *                           Signature: function(string $scope, int $scopeId = 0)
     * @param bool $includeDefault Update the default (global) scope as well (defaults to true)
     *
     * @return void
     */
    public function checkAndApplyAllScopes(string $path, callable $callback, bool $includeDefault = true): void
    {
        // Progressively increase scope so that override comparisons work as expected
        // Start with most specific (store scope) first
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->isSettingAppliedForScopeAndCode(
                $path,
                ScopeInterface::SCOPE_STORES,
                $store->getId()
            )) {
                $callback(ScopeInterface::SCOPE_STORES, $store->getId());
            }
        }

        // Websites
        foreach ($this->storeManager->getWebsites() as $website) {
            if ($this->isSettingAppliedForScopeAndCode(
                $path,
                ScopeInterface::SCOPE_WEBSITES,
                $website->getId()
            )) {
                $callback(ScopeInterface::SCOPE_WEBSITES, $website->getId());
            }
        }

        // Update the default configuration *last* so that earlier scope comparisons work as expected
        if ($includeDefault) {
            $callback(ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        }
    }


    /**
     * For a given path and scope determine which stores are affected
     * @param string $path The configuration path
     * @param string $scope The scope: `default`, `websites` or `stores`
     * @param int $scopeId The entity referenced by the corresponding scope
     * @return int[]
     * @throws NoSuchEntityException
     */
    public function getAffectedStoreIds(string $path, string $scope, int $scopeId): array
    {
        $storeIds = [];

        switch ($scope) {
            // check and find all stores that are not overridden
            case ScopeConfigInterface::SCOPE_TYPE_DEFAULT:
                foreach ($this->storeManager->getStores() as $store) {
                    if (!$this->isSettingAppliedForScopeAndCode(
                        $path,
                        ScopeInterface::SCOPE_STORES,
                        $store->getId()
                    )) {
                        $storeIds[] = $store->getId();
                    }
                }
                break;

            // website config applied - check and find all stores under that website that are not overridden
            case ScopeInterface::SCOPE_WEBSITES:
                $website = $this->websiteRepository->getById($scopeId);
                foreach ($website->getStores() as $store) {
                    if (!$this->isStoreSettingOverridingWebsite(
                        $path,
                        $website->getId(),
                        $store->getId()
                    )) {
                        $storeIds[] = $store->getId();
                    }
                }
                break;

            // simple store specific config
            case ScopeInterface::SCOPE_STORES:
                $storeIds[] = $scopeId;
        }
        return $storeIds;
    }
}
