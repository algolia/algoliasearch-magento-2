define(function () {
    return {
        getPagingParam() {
            return algoliaConfig.routing.pagingParameter;
        },
        getSortingParam() {
            return algoliaConfig.routing.sortingParameter;
        },
        getCategoryParam() {
            return algoliaConfig.routing.categoryParameter;
        },
        getPriceSeparator() {
            return algoliaConfig.routing.priceRouteSeparator;
        },
        getPriceParamValue(currentFacetAttribute, routeParameters) {
            if (!Object.hasOwn(routeParameters, currentFacetAttribute)) {
                return '';
            }

            return routeParameters.currentFacetAttribute;
        },
        getSortingValueFromUiState(uiStateProductIndex) {
            return uiStateProductIndex.sortBy;
        },
        getSortingFromRoute(routeParameters) {
            return routeParameters.sortBy;
        }
    }
});
