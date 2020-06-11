define(['jquery'], function ($) {
    'use strict';

    var algoliaAddToCartMixin = {

        submitForm: function (form) {
            if (window.algoliaConfig
                && algoliaConfig.ccAnalytics.enabled
                && algoliaConfig.ccAnalytics.conversionAnalyticsMode == 'place_order'
            ) {
                this._setQueryIdToForm(form)
            }

            this._super(form);
        },

        _setQueryIdToForm: function(form) {
            if (form.find('button[type="submit"]').length
                && form.find('button[type="submit"]').data('queryid')) {
                var queryID = form.find('button[type="submit"]').data('queryid');
            }

            var queryID = queryID || this._parseUrl('queryID');
            if (queryID.length === 0) {
                return;
            }

            if (form.find('input[name="queryid"]').length === 0) {
                form.prepend('<input type="hidden" name="queryID" />');
            }

            form.find('input[name="queryID"]').val(queryID);
        },

        _parseUrl: function(queryParamName) {
            var url = window.location.href;
            var regex = new RegExp('[?&]' + queryParamName + '(=([^&#]*)|&|#|$)');
            var results = regex.exec(url);
            if (!results || !results[2]) return '';

            return results[2];
        }

    };

    return function (widget) {
        $.widget('mage.catalogAddToCart', widget, algoliaAddToCartMixin);
        return $.mage.catalogAddToCart;
    };

});