<?php

namespace Algolia\AlgoliaSearch\Setup;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    private $config;

    private $productMetadata;

    private $defaultConfigData = [
        'algoliasearch_credentials/credentials/enable_backend' => '1',
        'algoliasearch_credentials/credentials/enable_frontend' => '1',
        'algoliasearch_credentials/credentials/application_id' => '',
        'algoliasearch_credentials/credentials/search_only_api_key' => '',
        'algoliasearch_credentials/credentials/api_key' => '',
        'algoliasearch_credentials/credentials/debug' => '0',
        'algoliasearch_credentials/credentials/index_prefix' => 'magento2_',

        'algoliasearch_autocomplete/autocomplete/is_popup_enabled' => '1',
        'algoliasearch_autocomplete/autocomplete/nb_of_products_suggestions' => '6',
        'algoliasearch_autocomplete/autocomplete/nb_of_categories_suggestions' => '2',
        'algoliasearch_autocomplete/autocomplete/nb_of_queries_suggestions' => '0',
        'algoliasearch_autocomplete/autocomplete/min_popularity' => '1000',
        'algoliasearch_autocomplete/autocomplete/min_number_of_results' => '2',
        'algoliasearch_autocomplete/autocomplete/render_template_directives' => '1',
        'algoliasearch_autocomplete/autocomplete/debug' => '0',

        'algoliasearch_instant/instant/is_instant_enabled' => '0',
        'algoliasearch_instant/instant/instant_selector' => '.columns',
        'algoliasearch_instant/instant/number_product_results' => '9',
        'algoliasearch_instant/instant/max_values_per_facet' => '10',
        'algoliasearch_instant/instant/replace_categories' => '1',
        'algoliasearch_instant/instant/show_suggestions_on_no_result_page' => '1',
        'algoliasearch_instant/instant/add_to_cart_enable' => '1',
        'algoliasearch_instant/instant/infinite_scroll_enable' => '0',

        'algoliasearch_products/products/use_adaptive_image' => '0',

        'algoliasearch_categories/categories/show_cats_not_included_in_navigation' => '0',
        'algoliasearch_categories/categories/index_empty_categories' => '0',

        'algoliasearch_images/image/width' => '265',
        'algoliasearch_images/image/height' => '265',
        'algoliasearch_images/image/type' => 'image',

        'algoliasearch_queue/queue/active' => '0',
        'algoliasearch_queue/queue/number_of_job_to_run' => '10',
        'algoliasearch_queue/queue/number_of_retries' => '3',

        'algoliasearch_cc_analytics/cc_analytics_group/enable' => '0',
        'algoliasearch_cc_analytics/cc_analytics_group/is_selector' => '.ais-hits--item a.result, .ais-infinite-hits--item a.result',
        'algoliasearch_cc_analytics/cc_analytics_group/enable_conversion_analytics' => 'disabled',
        'algoliasearch_cc_analytics/cc_analytics_group/add_to_cart_selector' => '.action.primary.tocart',

        'algoliasearch_analytics/analytics_group/enable' => '0',
        'algoliasearch_analytics/analytics_group/delay' => '3000',
        'algoliasearch_analytics/analytics_group/trigger_on_ui_interaction' => '1',
        'algoliasearch_analytics/analytics_group/push_initial_search' => '0',

        'algoliasearch_synonyms/synonyms_group/enable_synonyms' => '0',

        'algoliasearch_advanced/advanced/number_of_element_by_page' => '100',
        'algoliasearch_advanced/advanced/remove_words_if_no_result' => 'allOptional',
        'algoliasearch_advanced/advanced/partial_update' => '0',
        'algoliasearch_advanced/advanced/customer_groups_enable' => '0',
        'algoliasearch_advanced/advanced/make_seo_request' => '1',
        'algoliasearch_advanced/advanced/remove_branding' => '0',
        'algoliasearch_advanced/advanced/autocomplete_selector' => '.algolia-search-input',
        'algoliasearch_advanced/advanced/index_product_on_category_products_update' => '1',
        'algoliasearch_advanced/advanced/prevent_backend_rendering' => '0',
        'algoliasearch_advanced/advanced/prevent_backend_rendering_display_mode' => 'all',
        'algoliasearch_advanced/advanced/backend_rendering_allowed_user_agents' => "Googlebot\nBingbot",
    ];

    private $defaultArrayConfigData = [
        'algoliasearch_autocomplete/autocomplete/sections' => [
            [
                'name' => 'pages',
                'label' => 'Pages',
                'hitsPerPage' => '2',
            ],
        ],
        'algoliasearch_autocomplete/autocomplete/excluded_pages' => [
            [
                'attribute' => 'no-route',
            ],
        ],

        'algoliasearch_instant/instant/facets' => [
            [
                'attribute' => 'price',
                'type' => 'slider',
                'label' => 'Price',
                'searchable' => '2',
                'create_rule' => '2',
            ],
            [
                'attribute' => 'categories',
                'type' => 'conjunctive',
                'label' => 'Categories',
                'searchable' => '2',
                'create_rule' => '2',
            ],
            [
                'attribute' => 'color',
                'type' => 'disjunctive',
                'label' => 'Colors',
                'searchable' => '1',
                'create_rule' => '1',
            ],
        ],
        'algoliasearch_instant/instant/sorts' => [
            [
                'attribute' => 'price',
                'sort' => 'asc',
                'sortLabel' => 'Lowest price',
            ],
            [
                'attribute' => 'price',
                'sort' => 'desc',
                'sortLabel' => 'Highest price',
            ],
            [
                'attribute' => 'created_at',
                'sort' => 'desc',
                'sortLabel' => 'Newest first',
            ],
        ],

        'algoliasearch_products/products/product_additional_attributes' => [
            [
                'attribute' => 'name',
                'searchable' => '1',
                'order' => 'unordered',
                'retrievable' => '1',
            ],
            [
                'attribute' => 'sku',
                'searchable' => '1',
                'order' => 'unordered',
                'retrievable' => '1',
            ],
            [
                'attribute' => 'manufacturer',
                'searchable' => '1',
                'order' => 'unordered',
                'retrievable' => '1',
            ],
            [
                'attribute' => 'categories',
                'searchable' => '1',
                'order' => 'unordered',
                'retrievable' => '1',
            ],
            [
                'attribute' => 'color',
                'searchable' => '1',
                'order' => 'unordered',
                'retrievable' => '1',
            ],
            [
                'attribute' => 'price',
                'searchable' => '2',
                'order' => 'unordered',
                'retrievable' => '1',
            ],
            [
                'attribute' => 'rating_summary',
                'searchable' => '2',
                'order' => 'unordered',
                'retrievable' => '1',
            ],
        ],
        'algoliasearch_products/products/custom_ranking_product_attributes' => [
            [
                'attribute' => 'in_stock',
                'order' => 'desc',
            ],
            [
                'attribute' => 'ordered_qty',
                'order' => 'desc',
            ],
            [
                'attribute' => 'created_at',
                'order' => 'desc',
            ],
        ],

        'algoliasearch_categories/categories/category_additional_attributes' => [
            [
                'attribute' => 'name',
                'searchable' => '1',
                'order' => 'unordered',
                'retrievable' => '1',
            ],
            [
                'attribute' => 'path',
                'searchable' => '1',
                'order' => 'unordered',
                'retrievable' => '1',
            ],
            [
                'attribute' => 'meta_title',
                'searchable' => '1',
                'order' => 'unordered',
                'retrievable' => '1',
            ],
            [
                'attribute' => 'meta_keywords',
                'searchable' => '1',
                'order' => 'unordered',
                'retrievable' => '1',
            ],
            [
                'attribute' => 'meta_description',
                'searchable' => '1',
                'order' => 'unordered',
                'retrievable' => '1',
            ],
        ],
        'algoliasearch_categories/categories/custom_ranking_category_attributes' => [
            [
                'attribute' => 'product_count',
                'order' => 'desc',
            ],
        ],
    ];

    public function __construct(ConfigInterface $config, ProductMetadataInterface $productMetadata)
    {
        $this->config = $config;
        $this->productMetadata = $productMetadata;

        $this->serializeDefaultArrayConfigData();
        $this->mergeDefaultDataWithArrayData();
    }

    public function getDefaultConfigData()
    {
        return $this->defaultConfigData;
    }

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        /* MOVE TRANSFERED CONFIG DIRECTIVES */

        // In version 1.8.0 some config directives were moved to different config sections
        // This code preserves previously set config values for those directives
        // Notice: It needs to run before settings default config values

        $movedConfigDirectives = [
            'algoliasearch_credentials/credentials/use_adaptive_image' => 'algoliasearch_products/products/use_adaptive_image',
            'algoliasearch_products/products/number_product_results' => 'algoliasearch_instant/instant/number_product_results',
            'algoliasearch_products/products/show_suggestions_on_no_result_page' => 'algoliasearch_instant/instant/show_suggestions_on_no_result_page',
            'algoliasearch_credentials/credentials/is_popup_enabled' => 'algoliasearch_autocomplete/autocomplete/is_popup_enabled',
            'algoliasearch_credentials/credentials/is_instant_enabled' => 'algoliasearch_instant/instant/is_instant_enabled',
        ];

        $connection = $setup->getConnection();
        $table = $setup->getTable('core_config_data');
        foreach ($movedConfigDirectives as $from => $to) {
            try {
                $connection->query('UPDATE ' . $table . ' SET path = "' . $to . '" WHERE path = "' . $from . '"');
            } catch (\Magento\Framework\DB\Adapter\DuplicateException $e) {
                //
            }
        }

        /* SET DEFAULT CONFIG DATA */

        $table = $setup->getTable('core_config_data');
        $alreadyInserted = $setup->getConnection()
                                 ->query('SELECT path, value FROM ' . $table . ' WHERE path LIKE "algoliasearch_%"')
                                 ->fetchAll(\PDO::FETCH_KEY_PAIR);

        foreach ($this->defaultConfigData as $path => $value) {
            if (isset($alreadyInserted[$path])) {
                continue;
            }

            $this->config->saveConfig($path, $value, 'default', 0);
        }

        /* CREATE QUEUE DB TABLE */

        if (!$context->getVersion() || version_compare($context->getVersion(), '1.0.0') < 0) {
            $connection = $setup->getConnection();
            $table = $connection->newTable($setup->getTable('algoliasearch_queue'));

            $table->addColumn(
                'job_id',
                $table::TYPE_INTEGER,
                20,
                ['identity' => true, 'nullable' => false, 'primary' => true]
            );
            $table->addColumn('pid', $table::TYPE_INTEGER, 20, ['nullable' => true, 'default' => null]);
            $table->addColumn('class', $table::TYPE_TEXT, 50, ['nullable' => false]);
            $table->addColumn('method', $table::TYPE_TEXT, 50, ['nullable' => false]);
            $table->addColumn('data', $table::TYPE_TEXT, 5000, ['nullable' => false]);
            $table->addColumn('max_retries', $table::TYPE_INTEGER, 11, ['nullable' => false, 'default' => 3]);
            $table->addColumn('retries', $table::TYPE_INTEGER, 11, ['nullable' => false, 'default' => 0]);
            $table->addColumn('error_log', $table::TYPE_TEXT, null, ['nullable' => false]);
            $table->addColumn('data_size', $table::TYPE_INTEGER, 11, ['nullable' => true, 'default' => null]);

            $connection->createTable($table);
        }

        if (version_compare($context->getVersion(), '1.1.0') < 0) {
            $connection = $setup->getConnection();
            $connection->changeColumn(
                $setup->getTable('algoliasearch_queue'),
                'data',
                'data',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => '2M',
                ]
            );
        }

        if (version_compare($context->getVersion(), '1.3.0') < 0) {
            $connection = $setup->getConnection();

            $connection->addColumn(
                $setup->getTable('algoliasearch_queue'),
                'created',
                [
                    'type' => Table::TYPE_DATETIME,
                    'nullabled' => true,
                    'after' => 'job_id',
                    'comment' => 'Date and time of job creation',
                ]
            );

            // LOG TABLE
            $table = $connection->newTable($setup->getTable('algoliasearch_queue_log'));

            $table->addColumn('id', $table::TYPE_INTEGER, 20, [
                'identity' => true,
                'nullable' => false,
                'primary' => true,
            ]);
            $table->addColumn('started', $table::TYPE_DATETIME, null, ['nullable' => false]);
            $table->addColumn('duration', $table::TYPE_INTEGER, 20, ['nullable' => false]);
            $table->addColumn('processed_jobs', $table::TYPE_INTEGER, null, ['nullable' => false]);
            $table->addColumn('with_empty_queue', $table::TYPE_INTEGER, 1, ['nullable' => false]);

            $connection->createTable($table);
        }

        $setup->endSetup();
    }

    private function serializeDefaultArrayConfigData()
    {
        $serializeMethod = 'serialize';

        $magentoVersion = $this->productMetadata->getVersion();
        if (version_compare($magentoVersion, '2.2.0-dev', '>=') === true) {
            $serializeMethod = 'json_encode';
        }

        foreach ($this->defaultArrayConfigData as $path => $array) {
            $this->defaultArrayConfigData[$path] = $serializeMethod($array);
        }
    }

    private function mergeDefaultDataWithArrayData()
    {
        $this->defaultConfigData = array_merge($this->defaultConfigData, $this->defaultArrayConfigData);
    }
}
