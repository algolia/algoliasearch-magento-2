<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Data;

use Algolia\AlgoliaSearch\Helper\Configuration\AutocompleteHelper;
use Algolia\AlgoliaSearch\Model\Source\Suggestions;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchInterface;

class MigrateSuggestionsConfigPatch implements DataPatchInterface
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
     * Migrate legacy configurations
     * @return void
     */
    protected function moveIndexingSettings(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $configDataTable = $this->moduleDataSetup->getTable('core_config_data');

        // Get current number of configured Magento suggestions
        $whereConfigPathFrom = $connection->quoteInto('path = ?', AutocompleteHelper::NB_OF_QUERIES_SUGGESTIONS);
        $select = $connection->select()
            ->from($configDataTable)
            ->where($whereConfigPathFrom);
        $existingValues = $connection->fetchAll($select);

        foreach ($existingValues as $item) {
            // If number of suggestions used to be superior to zero, this means that the feature was activated
            // So we automatically set the suggestion mode to "Magento"
            if ((int) $item['value'] > 0) {
                $connection->insertOnDuplicate(
                    $configDataTable,
                    [
                        'scope' => $item['scope'],
                        'scope_id' => $item['scope_id'],
                        'path' => AutocompleteHelper::SUGGESTIONS_MODE,
                        'value' => Suggestions::SUGGESTIONS_MAGENTO
                    ]
                );
            }
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
