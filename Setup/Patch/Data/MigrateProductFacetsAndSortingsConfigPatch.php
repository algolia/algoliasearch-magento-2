<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Data;

use Algolia\AlgoliaSearch\Setup\Patch\DataMigrationTrait;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchInterface;

class MigrateProductFacetsAndSortingsConfigPatch implements DataPatchInterface
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
            'algoliasearch_instant/instant_facets/facets' => 'algoliasearch_products/products/facets',
            'algoliasearch_instant/instant_sorts/sorts' => 'algoliasearch_products/products/sorts',
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
