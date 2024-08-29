var config = {
    map   : {
        '*': {
            // Magento FE libs
            'algoliaCommon'       : 'Algolia_AlgoliaSearch/js/internals/common',
            'algoliaAutocomplete' : 'Algolia_AlgoliaSearch/js/autocomplete',
            'algoliaInstantSearch': 'Algolia_AlgoliaSearch/js/instantsearch',
            'algoliaInsights'     : 'Algolia_AlgoliaSearch/js/insights',
            'algoliaHooks'        : 'Algolia_AlgoliaSearch/js/hooks',

            // Autocomplete templates
            'productsHtml'   : 'Algolia_AlgoliaSearch/js/template/autocomplete/products',
            'pagesHtml'      : 'Algolia_AlgoliaSearch/js/template/autocomplete/pages',
            'categoriesHtml' : 'Algolia_AlgoliaSearch/js/template/autocomplete/categories',
            'suggestionsHtml': 'Algolia_AlgoliaSearch/js/template/autocomplete/suggestions',
            'additionalHtml' : 'Algolia_AlgoliaSearch/js/template/autocomplete/additional-section',

            // Recommend templates
            'recommendProductsHtml': 'Algolia_AlgoliaSearch/js/template/recommend/products',

            // Unbundling 
            'algoliaTemplateEngine': 'Algolia_AlgoliaSearch/js/internals/template-engine'
        }
    },
    paths : {
        'algoliaBundle'         : 'Algolia_AlgoliaSearch/js/internals/algoliaBundle.min',
        'algoliaSearchLib'      : 'Algolia_AlgoliaSearch/js/lib/algolia-search.min',
        'algoliaAutocompleteLib': 'Algolia_AlgoliaSearch/js/lib/algolia-autocomplete.min',
        'algoliaAnalytics'      : 'Algolia_AlgoliaSearch/js/internals/search-insights',
        'recommend'             : 'Algolia_AlgoliaSearch/js/internals/recommend.min',
        'recommendJs'           : 'Algolia_AlgoliaSearch/js/internals/recommend-js.min',
        'rangeSlider'           : 'Algolia_AlgoliaSearch/js/navigation/range-slider-widget',
        // Legacy 
        'algoliaHoganLib'       : 'Algolia_AlgoliaSearch/js/lib/hogan.min',
        'algoliaMustacheLib'    : 'Algolia_AlgoliaSearch/js/lib/mustache.min'
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
