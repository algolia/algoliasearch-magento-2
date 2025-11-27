<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Data;

use Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Model\Source\PaginationMode;
use Algolia\AlgoliaSearch\Model\Source\Suggestions;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchInterface;

class MigratePaginationConfigPatch implements DataPatchInterface
{
    public function __construct(
        protected ModuleDataSetupInterface $moduleDataSetup,
    )
    {
    }

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
     * Migrate legacy configurations
     * @return void
     */
    protected function moveIndexingSettings(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $configDataTable = $this->moduleDataSetup->getTable('core_config_data');

        // Get current number of configured Magento suggestions
        $select = $connection->select()
            ->from($configDataTable)
            ->where('path = ?', InstantSearchHelper::NUMBER_OF_PRODUCT_RESULTS);
        $existingValues = $connection->fetchAll($select);

        foreach ($existingValues as $item) {
            // If a custom pagination has already been specified before, set the pagination mode to custom
            $connection->insertOnDuplicate(
                $configDataTable,
                [
                    'scope' => $item['scope'],
                    'scope_id' => $item['scope_id'],
                    'path' => InstantSearchHelper::PAGINATION_MODE,
                    'value' => PaginationMode::PAGINATION_CUSTOM
                ]
            );
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
