<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Config;

class DefaultConfigProvider
{
    /**
     * @var string[]
     */
    protected $defaultConfigData = [
        'algoliasearch_indexing_manager/algolia_indexing/enable_indexing' => '1',
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
        'algoliasearch_queue/queue/number_of_job_to_run' => '5',
        'algoliasearch_queue/queue/number_of_retries' => '3',
        'algoliasearch_queue/queue/use_tmp_index' => '1',

        'algoliasearch_cc_analytics/cc_analytics_group/enable' => '0',
        'algoliasearch_cc_analytics/cc_analytics_group/is_selector' => '.ais-Hits-item a.result, .ais-InfiniteHits-item a.result',
        'algoliasearch_cc_analytics/cc_analytics_group/enable_conversion_analytics' => 'disabled',
        'algoliasearch_cc_analytics/cc_analytics_group/add_to_cart_selector' => '.action.primary.tocart',

        'algoliasearch_analytics/analytics_group/enable' => '0',
        'algoliasearch_analytics/analytics_group/delay' => '3000',
        'algoliasearch_analytics/analytics_group/trigger_on_ui_interaction' => '1',
        'algoliasearch_analytics/analytics_group/push_initial_search' => '0',

        'algoliasearch_synonyms/synonyms_group/enable_synonyms' => '0',

        'algoliasearch_advanced/advanced/number_of_element_by_page' => '300',
        'algoliasearch_advanced/advanced/remove_words_if_no_result' => 'allOptional',
        'algoliasearch_advanced/advanced/partial_update' => '0',
        'algoliasearch_advanced/advanced/customer_groups_enable' => '0',
        'algoliasearch_advanced/advanced/remove_branding' => '0',
        'algoliasearch_autocomplete/autocomplete/autocomplete_selector' => '.algolia-search-input',
        'algoliasearch_advanced/advanced/index_product_on_category_products_update' => '1',
        'algoliasearch_advanced/advanced/prevent_backend_rendering' => '0',
        'algoliasearch_advanced/advanced/prevent_backend_rendering_display_mode' => 'all',
        'algoliasearch_advanced/advanced/backend_rendering_allowed_user_agents' => "Googlebot\nBingbot",
        'algoliasearch_advanced/queue/archive_clear_limit' => '30',
    ];

    /**
     * @var string[][][]
     */
    protected $defaultArrayConfigData = [
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

        'algoliasearch_instant/instant_facets/facets' => [
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
                'create_rule' => '2',
            ],
        ],
        'algoliasearch_instant/instant_sorts/sorts' => [
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
            [
                'attribute' => 'product_count',
                'searchable' => '2',
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

    public function __construct()
    {
        $this->serializeDefaultArrayConfigData();
        $this->mergeDefaultDataWithArrayData();
    }

    /**
     * @return string[]
     */
    public function getDefaultConfigData(): array
    {
        return $this->defaultConfigData;
    }

    /**
     * @return void
     */
    protected function serializeDefaultArrayConfigData(): void
    {
        foreach ($this->defaultArrayConfigData as $path => $array) {
            $this->defaultArrayConfigData[$path] = json_encode($array);
        }
    }

    /**
     * @return void
     */
    protected function mergeDefaultDataWithArrayData(): void
    {
        $this->defaultConfigData = array_merge($this->defaultConfigData, $this->defaultArrayConfigData);
    }
}
