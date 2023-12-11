var config = {
    map   : {
        '*': {
            // Magento FE libs
            'algoliaCommon'       : 'Algolia_AlgoliaSearch/internals/common',
            'algoliaAutocomplete' : 'Algolia_AlgoliaSearch/autocomplete',
            'algoliaInstantSearch': 'Algolia_AlgoliaSearch/instantsearch',
            'algoliaInsights'     : 'Algolia_AlgoliaSearch/insights',
            'algoliaHooks'        : 'Algolia_AlgoliaSearch/hooks',

            // Autocomplete templates
            'productsHtml'   : 'Algolia_AlgoliaSearch/internals/template/autocomplete/products',
            'pagesHtml'      : 'Algolia_AlgoliaSearch/internals/template/autocomplete/pages',
            'categoriesHtml' : 'Algolia_AlgoliaSearch/internals/template/autocomplete/categories',
            'suggestionsHtml': 'Algolia_AlgoliaSearch/internals/template/autocomplete/suggestions',
            'additionalHtml' : 'Algolia_AlgoliaSearch/internals/template/autocomplete/additional-section',

            // Recommend templates
            'recommendProductsHtml': 'Algolia_AlgoliaSearch/internals/template/recommend/products'
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
                'Algolia_AlgoliaSearch/insights/add-to-cart-mixin': true
            }
        }
    }
};
