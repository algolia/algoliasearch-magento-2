<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchInterface;

class MigrateInstantSearchConfigPatch implements DataPatchInterface
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

        $this->moveInstantSearchSettings();

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * Migrate old InstantSearch configurations to new groupings
     * @return void
     */
    protected function moveInstantSearchSettings(): void
    {
        $movedConfig = [
            'algoliasearch_instant/instant/facets'                             => 'algoliasearch_instant/instant_facets/facets',
            'algoliasearch_instant/instant/max_values_per_facet'               => 'algoliasearch_instant/instant_facets/max_values_per_facet',

            'algoliasearch_instant/instant/sorts'                              => 'algoliasearch_instant/instant_sorts/sorts',

            'algoliasearch_instant/instant/instantsearch_searchbox'            => 'algoliasearch_instant/instant_options/instantsearch_searchbox',
            'algoliasearch_instant/instant/show_suggestions_on_no_result_page' => 'algoliasearch_instant/instant_options/show_suggestions_on_no_result_page',
            'algoliasearch_instant/instant/add_to_cart_enable'                 => 'algoliasearch_instant/instant_options/add_to_cart_enable',
            'algoliasearch_instant/instant/infinite_scroll_enable'             => 'algoliasearch_instant/instant_options/infinite_scroll_enable',
            'algoliasearch_instant/instant/hide_pagination'                    => 'algoliasearch_instant/instant_options/hide_pagination'
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
