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
            'productsHtml'   : 'Algolia_AlgoliaSearch/js/internals/template/autocomplete/products',
            'pagesHtml'      : 'Algolia_AlgoliaSearch/js/internals/template/autocomplete/pages',
            'categoriesHtml' : 'Algolia_AlgoliaSearch/js/internals/template/autocomplete/categories',
            'suggestionsHtml': 'Algolia_AlgoliaSearch/js/internals/template/autocomplete/suggestions',
            'additionalHtml' : 'Algolia_AlgoliaSearch/js/internals/template/autocomplete/additional-section',

            // Recommend templates
            'recommendProductsHtml': 'Algolia_AlgoliaSearch/js/internals/template/recommend/products'
        }
    },
    paths : {
        'algoliaBundle'   : 'Algolia_AlgoliaSearch/internals/algoliaBundle.min',
        'algoliaAnalytics': 'Algolia_AlgoliaSearch/internals/search-insights',
        'recommend'       : 'Algolia_AlgoliaSearch/internals/recommend.min',
        'recommendJs'     : 'Algolia_AlgoliaSearch/internals/recommend-js.min',
        'rangeSlider'     : 'Algolia_AlgoliaSearch/navigation/range-slider-widget',
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
    }
};
