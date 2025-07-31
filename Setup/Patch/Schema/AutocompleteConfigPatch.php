<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Schema;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;

class AutocompleteConfigPatch implements SchemaPatchInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ConfigInterface $config
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ConfigInterface $config,
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->config = $config;
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply()
    {
        $movedConfigDirectives = [
            'algoliasearch_advanced/advanced/autocomplete_selector' => 'algoliasearch_autocomplete/autocomplete/autocomplete_selector',
        ];
        $this->moduleDataSetup->getConnection()->startSetup();
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');
        foreach ($movedConfigDirectives as $from => $to) {
            try {
                $connection->query('UPDATE ' . $table . ' SET path = "' . $to . '" WHERE path = "' . $from . '"');
            } catch (\Magento\Framework\DB\Adapter\DuplicateException) {
                //
            }
        }
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @return array|string[]
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @return array|string[]
     */
    public function getAliases()
    {
        return [];
    }
}
