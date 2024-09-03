const config = {
    map   : {
        '*': {
            // Magento FE libs
            'algoliaCommon'       : 'Algolia_AlgoliaSearch/js/internals/common',
            'algoliaAutocomplete' : 'Algolia_AlgoliaSearch/js/autocomplete',
            'algoliaInstantSearch': 'Algolia_AlgoliaSearch/js/instantsearch',
            'algoliaInsights'     : 'Algolia_AlgoliaSearch/js/insights',
            'algoliaHooks'        : 'Algolia_AlgoliaSearch/js/hooks',

            // Autocomplete templates
            'algoliaAutocompleteProductsHtml'   : 'Algolia_AlgoliaSearch/js/template/autocomplete/products',
            'algoliaAutocompletePagesHtml'      : 'Algolia_AlgoliaSearch/js/template/autocomplete/pages',
            'algoliaAutocompleteCategoriesHtml' : 'Algolia_AlgoliaSearch/js/template/autocomplete/categories',
            'algoliaAutocompleteSuggestionsHtml': 'Algolia_AlgoliaSearch/js/template/autocomplete/suggestions',
            'algoliaAutocompleteAdditionalHtml' : 'Algolia_AlgoliaSearch/js/template/autocomplete/additional-section',

            // Recommend templates
            'algoliaRecommendProductsHtml': 'Algolia_AlgoliaSearch/js/template/recommend/products',

            // Unbundling
            'algoliaTemplateEngine': 'Algolia_AlgoliaSearch/js/internals/template-engine'
        }
    },
    paths : {
        // Core Search UI libs
        'algoliaSearchLib'                : 'Algolia_AlgoliaSearch/js/lib/algolia-search.min',
        'algoliaInstantSearchLib'         : 'Algolia_AlgoliaSearch/js/lib/algolia-instantsearch.min',
        'algoliaAutocompleteLib'          : 'Algolia_AlgoliaSearch/js/lib/algolia-autocomplete.min',
        'algoliaAnalyticsLib'             : 'Algolia_AlgoliaSearch/js/lib/search-insights.min',
        'algoliaRecommendLib'             : 'Algolia_AlgoliaSearch/js/lib/recommend.min',
        'algoliaRecommendJsLib'           : 'Algolia_AlgoliaSearch/js/lib/recommend-js.min',

        // Autocomplete plugins
        'algoliaQuerySuggestionsPluginLib': 'Algolia_AlgoliaSearch/js/lib/query-suggestions-plugin.min',

        // Legacy
        'algoliaMustacheLib': 'Algolia_AlgoliaSearch/js/lib/mustache.min',
        'algoliaHoganLib'   : 'Algolia_AlgoliaSearch/js/lib/hogan.min',

        // DEPRECATED
        'algoliaBundle'        : 'Algolia_AlgoliaSearch/js/internals/algoliaBundle.min',
        'rangeSlider'          : 'Algolia_AlgoliaSearch/js/navigation/range-slider-widget',
        'recommend'            : 'Algolia_AlgoliaSearch/js/lib/recommend.min',
        'algoliaAnalytics'     : 'Algolia_AlgoliaSearch/js/lib/search-insights.min',
        'recommendJs'          : 'Algolia_AlgoliaSearch/js/lib/recommend-js.min',
        'productsHtml'         : 'Algolia_AlgoliaSearch/js/template/autocomplete/products',
        'pagesHtml'            : 'Algolia_AlgoliaSearch/js/template/autocomplete/pages',
        'categoriesHtml'       : 'Algolia_AlgoliaSearch/js/template/autocomplete/categories',
        'suggestionsHtml'      : 'Algolia_AlgoliaSearch/js/template/autocomplete/suggestions',
        'additionalHtml'       : 'Algolia_AlgoliaSearch/js/template/autocomplete/additional-section',
        'recommendProductsHtml': 'Algolia_AlgoliaSearch/js/template/recommend/products',
    },
    deps  : [
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
    shim: {
        'algoliaHoganLib': {
            exports: 'Hogan'
        }
    }
};
