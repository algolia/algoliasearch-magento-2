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
            // Guard against prototype pollution
            if (Object.hasOwn(Object.prototype, currentFacetAttribute)) {
                return '';
            }

            // eslint-disable-next-line security/detect-object-injection
            return routeParameters[currentFacetAttribute];
        },
        getSortingValueFromUiState(uiStateProductIndex) {
            return uiStateProductIndex.sortBy;
        },
        getSortingFromRoute(routeParameters) {
            return routeParameters.sortBy;
        }
    }
});
