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
        getPriceParam() {
            return algoliaConfig.routing.priceParameter;
        },
        getPriceDelimiter() {
            return algoliaConfig.routing.priceRouteDelimiter;
        },
        getSortingValueFromUiState(uiStateProductIndex) {
            return uiStateProductIndex.sortBy;
        },
        getSortingFromRoute(routeParameters) {
            return routeParameters.sortBy;
        }
    }
});
