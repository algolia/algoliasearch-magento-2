define(function () {
    'use strict';

    window.transformHit = function (hit, price_key, helper) {
        if (Array.isArray(hit.categories))
            hit.categories = hit.categories.join(', ');

        if (hit._highlightResult.categories_without_path && Array.isArray(hit.categories_without_path)) {
            hit.categories_without_path = hit._highlightResult.categories_without_path.map(function (category) {
                return category.value;
            });

            hit.categories_without_path = hit.categories_without_path.join(', ');
        }

        var matchedColors = [];

        if (helper && algoliaConfig.useAdaptiveImage === true) {
            if (hit.images_data && helper.state.facetsRefinements.color) {
                matchedColors = helper.state.disjunctiveFacetsRefinements.color.slice(0); // slice to clone
            }

            if (hit.images_data && helper.state.disjunctiveFacetsRefinements.color) {
                matchedColors = helper.state.disjunctiveFacetsRefinements.color.slice(0); // slice to clone
            }
        }

        if (Array.isArray(hit.color)) {
            var colors = [];

            hit._highlightResult.color.forEach(hit._highlightResult.color, function (color, i) {
                if (color.matchLevel === 'none') {
                    return;
                }

                colors.push(color.value);

                if (algoliaConfig.useAdaptiveImage === true) {
                    var re = /<em>(.*?)<\/em>/g;
                    var matchedWords = color.value.match(re).map(function (val) {
                        return val.replace(/<\/?em>/g, '');
                    });

                    var matchedColor = matchedWords.join(' ');

                    if (hit.images_data && color.fullyHighlighted && color.fullyHighlighted === true) {
                        matchedColors.push(matchedColor);
                    }
                }
            });

            colors = colors.join(', ');

            hit._highlightResult.color = { value: colors };
        }
        else {
            if (hit._highlightResult.color && hit._highlightResult.color.matchLevel === 'none') {
                hit._highlightResult.color = { value: '' };
            }
        }

        if (algoliaConfig.useAdaptiveImage === true) {
            matchedColors.forEach(matchedColors, function (color, i) {
                color = color.toLowerCase();

                if (hit.images_data[color]) {
                    hit.image_url = hit.images_data[color];
                    hit.thumbnail_url = hit.images_data[color];

                    return false;
                }
            });
        }

        if (hit._highlightResult.color && hit._highlightResult.color.value && hit.categories_without_path) {
            if (hit.categories_without_path.indexOf('<em>') === -1 && hit._highlightResult.color.value.indexOf('<em>') !== -1) {
                hit.categories_without_path = '';
            }
        }

        if (Array.isArray(hit._highlightResult.name))
            hit._highlightResult.name = hit._highlightResult.name[0];

        if (Array.isArray(hit.price))
            hit.price = hit.price[0];

        if (hit['price'] !== undefined && price_key !== '.' + algoliaConfig.currencyCode + '.default' && hit['price'][algoliaConfig.currencyCode][price_key.substr(1) + '_formated'] !== hit['price'][algoliaConfig.currencyCode]['default_formated']) {
            hit['price'][algoliaConfig.currencyCode][price_key.substr(1) + '_original_formated'] = hit['price'][algoliaConfig.currencyCode]['default_formated'];
        }
            
        if (hit['price'][algoliaConfig.currencyCode]['default_original_formated']
            && hit['price'][algoliaConfig.currencyCode]['special_to_date']) {
            var priceExpiration = hit['price'][algoliaConfig.currencyCode]['special_to_date'];
                
            if (algoliaConfig.now > priceExpiration + 1) {
                hit['price'][algoliaConfig.currencyCode]['default_formated'] = hit['price'][algoliaConfig.currencyCode]['default_original_formated'];
                hit['price'][algoliaConfig.currencyCode]['default_original_formated'] = false;
            }
        }

        // Add to cart parameters
        var action = algoliaConfig.instant.addToCartParams.action + 'product/' + hit.objectID + '/';

        var correctFKey = getCookie('form_key');

        if(correctFKey != "" && algoliaConfig.instant.addToCartParams.formKey != correctFKey) {
            algoliaConfig.instant.addToCartParams.formKey = correctFKey;
        }

        hit.addToCart = {
            'action': action,
            'uenc': AlgoliaBase64.mageEncode(action),
            'formKey': algoliaConfig.instant.addToCartParams.formKey
        };

        return hit;
    };

    return window.transformHit;
});