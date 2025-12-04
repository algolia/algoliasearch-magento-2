define(function () {
    const PAGING_PARAMETER = "page";
    const SORTING_PARAMETER = "sortBy";

    return {
        getPagingParam: function() {
            return PAGING_PARAMETER;
        },
        getSortingParam: function() {
            return SORTING_PARAMETER;
        },
        getSortingValueFromUiState: function(uiStateProductIndex) {
            return uiStateProductIndex.sortBy;
        },
        getSortingFromRoute: function(routeParameters) {
            return routeParameters.sortBy;
        }
    }
});
