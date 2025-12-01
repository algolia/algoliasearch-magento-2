<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Data;

use Algolia\AlgoliaSearch\Setup\Patch\DataMigrationTrait;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchInterface;

class MigrateLegacyConfigPatch implements DataPatchInterface
{
    use DataMigrationTrait;

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
        $movedConfig = [
            'algoliasearch_credentials/credentials/use_adaptive_image' => 'algoliasearch_products/products/use_adaptive_image',
            'algoliasearch_products/products/number_product_results' => 'algoliasearch_instant/instant/number_product_results',
            'algoliasearch_products/products/show_suggestions_on_no_result_page' => 'algoliasearch_instant/instant/show_suggestions_on_no_result_page',
            'algoliasearch_credentials/credentials/is_popup_enabled' => 'algoliasearch_autocomplete/autocomplete/is_popup_enabled',
            'algoliasearch_credentials/credentials/is_instant_enabled' => 'algoliasearch_instant/instant/is_instant_enabled',
       ];

        $this->migrateConfig($movedConfig);
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
