<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Data;

use Algolia\AlgoliaSearch\Helper\Configuration\MerchandisingStudioHelper;
use Algolia\AlgoliaSearch\Setup\Patch\DataMigrationTrait;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchInterface;

class MigrateVisualMerchandiserConfig implements DataPatchInterface
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
     * Migrate old Visual Merchandiser configurations
     */
    protected function moveIndexingSettings(): void
    {
        $movedConfig = [
            'algoliasearch_products/products/enable_visual_merchandising' =>
                MerchandisingStudioHelper::ENABLE_VISUAL_MERCHANDISING,
            'algoliasearch_products/products/category_page_id_attribute_name' =>
                MerchandisingStudioHelper::CATEGORY_PAGE_ID_ATTRIBUTE_NAME,
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
