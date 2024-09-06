const config = {
    map: {
        '*': {
            // Magento FE libs
            'algoliaCommon'       : 'Algolia_AlgoliaSearch/js/internals/common',
            'algoliaAutocomplete' : 'Algolia_AlgoliaSearch/js/autocomplete',
            'algoliaInstantSearch': 'Algolia_AlgoliaSearch/js/instantsearch',
            'algoliaInsights'     : 'Algolia_AlgoliaSearch/js/insights',
            'algoliaHooks'        : 'Algolia_AlgoliaSearch/js/hooks',

            // Unbundled template processor
            'algoliaTemplateEngine': 'Algolia_AlgoliaSearch/js/internals/template-engine',

            // DEPRECATED - migrated to new paths - these will be removed in a future release
            'algoliaAnalytics'     : 'algoliaAnalyticsLib',
            'recommend'            : 'algoliaRecommendLib',
            'recommendJs'          : 'algoliaRecommendJsLib',
            'productsHtml'         : 'algoliaAutocompleteProductsHtml',
            'pagesHtml'            : 'algoliaAutocompletePagesHtml',
            'categoriesHtml'       : 'algoliaAutocompleteCategoriesHtml',
            'suggestionsHtml'      : 'algoliaAutocompleteSuggestionsHtml',
            'additionalHtml'       : 'algoliaAutocompleteAdditionalHtml',
            'recommendProductsHtml': 'algoliaRecommendProductsHtml'
        }
    },
    paths: {
        // Core Search UI libs
        'algoliaSearchLib'       : 'Algolia_AlgoliaSearch/js/lib/algolia-search.min',
        'algoliaInstantSearchLib': 'Algolia_AlgoliaSearch/js/lib/algolia-instantsearch.min',
        'algoliaAutocompleteLib' : 'Algolia_AlgoliaSearch/js/lib/algolia-autocomplete.min',
        'algoliaAnalyticsLib'    : 'Algolia_AlgoliaSearch/js/lib/search-insights.min',
        'algoliaRecommendLib'    : 'Algolia_AlgoliaSearch/js/lib/recommend.min',
        'algoliaRecommendJsLib'  : 'Algolia_AlgoliaSearch/js/lib/recommend-js.min',

        // Autocomplete plugins
        'algoliaQuerySuggestionsPluginLib'  : 'Algolia_AlgoliaSearch/js/lib/query-suggestions-plugin.min',
        'algoliaInsightsPluginLib'          : 'Algolia_AlgoliaSearch/js/lib/insights-plugin.min',
        'algoliaRecentSearchesLib'          : 'Algolia_AlgoliaSearch/js/lib/recent-searches-plugin.min',

        // Autocomplete templates
        'algoliaAutocompleteProductsHtml'   : 'Algolia_AlgoliaSearch/js/template/autocomplete/products',
        'algoliaAutocompletePagesHtml'      : 'Algolia_AlgoliaSearch/js/template/autocomplete/pages',
        'algoliaAutocompleteCategoriesHtml' : 'Algolia_AlgoliaSearch/js/template/autocomplete/categories',
        'algoliaAutocompleteSuggestionsHtml': 'Algolia_AlgoliaSearch/js/template/autocomplete/suggestions',
        'algoliaAutocompleteAdditionalHtml' : 'Algolia_AlgoliaSearch/js/template/autocomplete/additional-section',

        // Recommend templates
        'algoliaRecommendProductsHtml': 'Algolia_AlgoliaSearch/js/template/recommend/products',

        // Parser libs for legacy templating
        'algoliaMustacheLib': 'Algolia_AlgoliaSearch/js/lib/mustache.min',
        'algoliaHoganLib'   : 'Algolia_AlgoliaSearch/js/lib/hogan.min',

        // DEPRECATED - to be removed in a future release
        'algoliaBundle': 'Algolia_AlgoliaSearch/js/internals/algoliaBundle.min',
        'rangeSlider'  : 'Algolia_AlgoliaSearch/js/navigation/range-slider-widget'

    },
    deps : [
        'algoliaInstantSearch',
        'algoliaInsights'
    ],
    config: {
        mixins: {
            'Magento_Catalog/js/catalog-add-to-cart': {
                'Algolia_AlgoliaSearch/js/insights/add-to-cart-mixin': true
            }
        }
    },
    shim : {
        'algoliaHoganLib': {
            exports: 'Hogan'
        }
    }
};
