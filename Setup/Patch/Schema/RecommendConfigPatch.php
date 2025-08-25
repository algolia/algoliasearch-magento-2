<?php

namespace Algolia\AlgoliaSearch\Setup\Patch\Schema;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;

class RecommendConfigPatch implements SchemaPatchInterface
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

    /**
     * @return RecommendConfigPatch|void
     */
    public function apply()
    {
        $movedConfigDirectives = [
            'algoliasearch_recommend/recommend/is_frequently_bought_together_enabled' => 'algoliasearch_recommend/recommend/frequently_bought_together/is_frequently_bought_together_enabled',
            'algoliasearch_recommend/recommend/is_related_products_enabled' => 'algoliasearch_recommend/recommend/related_product/is_related_products_enabled',
            'algoliasearch_recommend/recommend/num_of_frequently_bought_together_products' => 'algoliasearch_recommend/recommend/frequently_bought_together/num_of_frequently_bought_together_products',
            'algoliasearch_recommend/recommend/num_of_related_products' => 'algoliasearch_recommend/recommend/related_product/num_of_related_products',
            'algoliasearch_recommend/recommend/is_remove_core_related_products_block' => 'algoliasearch_recommend/recommend/related_product/is_remove_core_related_products_block',
            'algoliasearch_recommend/recommend/is_remove_core_upsell_products_block' => 'algoliasearch_recommend/recommend/frequently_bought_together/is_remove_core_upsell_products_block'
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
}
