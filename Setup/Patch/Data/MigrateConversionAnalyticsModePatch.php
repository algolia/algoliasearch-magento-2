<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Data;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\ConfigChecker;
use Algolia\AlgoliaSearch\Helper\InsightsHelper;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchInterface;
use Magento\Store\Model\StoreManagerInterface;

class MigrateConversionAnalyticsModePatch implements DataPatchInterface
{
    public function __construct(
        protected ModuleDataSetupInterface $moduleDataSetup,
        protected WriterInterface          $configWriter,
        protected ConfigInterface          $config,
        protected ScopeConfigInterface     $scopeConfig,
        protected ConfigChecker            $configChecker,
        protected StoreManagerInterface    $storeManager
    )
    {}

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
    public function apply(): PatchInterface
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $this->configChecker->checkAndApplyAllScopes(ConfigHelper::CC_CONVERSION_ANALYTICS_MODE, [$this, 'migrateSetting']);

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * Pass as a callback to ConfigChecker to ensure that all scopes are checked
     * @param string $scope
     * @param int $scopeId
     * @return void
     */
    public function migrateSetting(string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, int $scopeId = 0): void
    {
        $value = $this->scopeConfig->getValue(ConfigHelper::CC_CONVERSION_ANALYTICS_MODE, $scope, $scopeId);
        if (in_array(
            $value,
            [
                InsightsHelper::CONVERSION_ANALYTICS_MODE_CART,
                InsightsHelper::CONVERSION_ANALYTICS_MODE_PURCHASE
            ]
        )) {
            $this->configWriter->save(
                ConfigHelper::CC_CONVERSION_ANALYTICS_MODE,
                InsightsHelper::CONVERSION_ANALYTICS_MODE_ALL,
                $scope,
                $scopeId);
        }
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
