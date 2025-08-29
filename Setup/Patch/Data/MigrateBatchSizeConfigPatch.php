<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Data;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchInterface;

class MigrateBatchSizeConfigPatch implements DataPatchInterface
{
    public function __construct(
        protected ModuleDataSetupInterface $moduleDataSetup,
    ) {}

    /**
     * @inheritDoc
     */
    public function apply(): PatchInterface
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $this->moveIndexingSettings();

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * Migrate old Indexing configurations
     * @return void
     */
    protected function moveIndexingSettings(): void
    {
        $movedConfig = [
            'algoliasearch_advanced/queue/number_of_element_by_page' => ConfigHelper::NUMBER_OF_ELEMENT_BY_PAGE,
       ];

        $connection = $this->moduleDataSetup->getConnection();
        foreach ($movedConfig as $from => $to) {
            $configDataTable = $this->moduleDataSetup->getTable('core_config_data');
            $whereConfigPath = $connection->quoteInto('path = ?', $from);
            $connection->update($configDataTable, ['path' => $to], $whereConfigPath);
        }
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
