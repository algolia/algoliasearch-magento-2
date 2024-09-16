define(['jquery', 'algoliaInstantSearchLib', 'algoliaBase64'], function ($, instantsearch, algoliaBase64) {
    const USE_GLOBALS = true;

    // Character maps supplied for more performant Regex ops
    const SPECIAL_CHAR_ENCODE_MAP = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };

    /// Reverse key / value pair
    const SPECIAL_CHAR_DECODE_MAP = Object.entries(SPECIAL_CHAR_ENCODE_MAP).reduce((acc, [key, value]) => {
        acc[value] = key;
        return acc;
    }, {});

    const algolia = {
        deprecatedHooks: [
            'beforeAutocompleteProductSourceOptions',
            'beforeAutocompleteSources'
        ],
        allowedHooks: [
            'beforeAutocompleteSources', // Older implementations incompatible with v1 API
            'afterAutocompleteSources',
            'afterAutocompletePlugins',
            'beforeAutocompleteOptions',
            'afterAutocompleteOptions',
            'afterAutocompleteStart',
            'beforeAutocompleteProductSourceOptions',
            'afterAutocompleteProductSourceOptions',
            'beforeInstantsearchInit',
            'beforeWidgetInitialization',
            'beforeInstantsearchStart',
            'afterInstantsearchStart',
            'afterInsightsBindEvents'
        ],
        registeredHooks: [],
        registerHook: function (hookName, callback) {
            if (this.allowedHooks.indexOf(hookName) === -1) {
                throw 'Hook "' + hookName + '" cannot be defined. Please use one of ' + this.allowedHooks.join(', ');
            }

            if (this.deprecatedHooks.indexOf(hookName) > -1) {
                console.warn(`Algolia Autocomplete: ${hookName} has been deprecated and may not be supported in a future release.`);
            }

            if (!this.registeredHooks[hookName]) {
                this.registeredHooks[hookName] = [callback];
            } else {
                this.registeredHooks[hookName].push(callback);
            }
        },
        getRegisteredHooks: function (hookName) {
            if (this.allowedHooks.indexOf(hookName) === -1) {
                throw 'Hook "' + hookName + '" cannot be defined. Please use one of ' + this.allowedHooks.join(', ');
            }

            if (!this.registeredHooks[hookName]) {
                return [];
            }

            return this.registeredHooks[hookName];
        },
        triggerHooks: function () {
            var hookName = arguments[0],
                originalData = arguments[1],
                hookArguments = Array.prototype.slice.call(arguments, 2);

            // console.log("Invoking hook", hookName);

            var data = this.getRegisteredHooks(hookName).reduce(function (currentData, hook) {
                if (Array.isArray(currentData)) {
                    currentData = [currentData];
                }
                var allParameters = [].concat(currentData).concat(hookArguments);
                return hook.apply(null, allParameters);
            }, originalData);

            return data;
        },
        htmlspecialcharsDecode: string => {
            const regex = new RegExp(Object.keys(SPECIAL_CHAR_DECODE_MAP).join('|'), 'g');
            return string.replace(regex, m => SPECIAL_CHAR_DECODE_MAP[m]);
        },
        htmlspecialcharsEncode: string => {
            const regex = new RegExp(`[${Object.keys(SPECIAL_CHAR_ENCODE_MAP).join('')}]`, 'g');
            return string.replace(regex, (m) => SPECIAL_CHAR_ENCODE_MAP[m]);
        }
    };

    // The url is now rendered as follows : http://website.com?q=searchquery&facet1=value&facet2=value1~value2
    // "?" and "&" are used to be fetched easily inside Magento for the backend rendering
    // Multivalued facets use "~" as separator
    // Targeted index is defined by sortBy parameter
    const routing = {
        router: instantsearch.routers.history({
            parseURL: function (qsObject) {
                var location = qsObject.location,
                    qsModule = qsObject.qsModule;
                const queryString = location.hash ? location.hash : location.search;
                return qsModule.parse(queryString.slice(1))
            },
            createURL: function (qsObject) {
                var qsModule = qsObject.qsModule,
                    routeState = qsObject.routeState,
                    location = qsObject.location;
                const protocol = location.protocol,
                    hostname = location.hostname,
                    port = location.port ? location.port : '',
                    pathname = location.pathname,
                    hash = location.hash;

                const queryString = qsModule.stringify(routeState);
                const portWithPrefix = port === '' ? '' : ':' + port;
                // IE <= 11 has no location.origin or buggy. Therefore we don't rely on it
                if (!routeState || Object.keys(routeState).length === 0) {
                    return protocol + '//' + hostname + portWithPrefix + pathname;
                } else {
                    if (queryString && queryString != 'q=__empty__') {
                        return protocol + '//' + hostname + portWithPrefix + pathname + '?' + queryString;
                    } else {
                        return protocol + '//' + hostname + portWithPrefix + pathname;
                    }
                }
            },
        }),
        stateMapping: {
            stateToRoute: function (uiState) {
                var productIndexName = algoliaConfig.indexName + '_products';
                var uiStateProductIndex = uiState[productIndexName] || {};
                var routeParameters = {};
                if (algoliaConfig.isCategoryPage) {
                    routeParameters['q'] = uiState[productIndexName].query;
                } else if (algoliaConfig.isLandingPage) {
                    routeParameters['q'] = uiState[productIndexName].query || algoliaConfig.landingPage.query || '__empty__';
                } else {
                    routeParameters['q'] = uiState[productIndexName].query || algoliaConfig.request.query || '__empty__';
                }
                if (algoliaConfig.facets) {
                    for (var i = 0; i < algoliaConfig.facets.length; i++) {
                        var currentFacet = algoliaConfig.facets[i];
                        // Handle refinement facets
                        if (currentFacet.attribute != 'categories' && (currentFacet.type == 'conjunctive' || currentFacet.type == 'disjunctive')) {
                            routeParameters[currentFacet.attribute] = (uiStateProductIndex.refinementList &&
                                uiStateProductIndex.refinementList[currentFacet.attribute] &&
                                uiStateProductIndex.refinementList[currentFacet.attribute].join('~'));
                        }
                        // Handle categories
                        if (currentFacet.attribute == 'categories' && !algoliaConfig.isCategoryPage) {
                            routeParameters[currentFacet.attribute] = (uiStateProductIndex.hierarchicalMenu &&
                                uiStateProductIndex.hierarchicalMenu[currentFacet.attribute + '.level0'] &&
                                uiStateProductIndex.hierarchicalMenu[currentFacet.attribute + '.level0'].join('~'));
                        }
                        // Handle sliders
                        if (currentFacet.type == 'slider' || currentFacet.type == 'priceRanges') {
                            routeParameters[currentFacet.attribute] = (uiStateProductIndex.range &&
                                uiStateProductIndex.range[currentFacet.attribute] &&
                                uiStateProductIndex.range[currentFacet.attribute]);
                        }
                    }

                }
                routeParameters['sortBy'] = uiStateProductIndex.sortBy;
                routeParameters['page'] = uiStateProductIndex.page;
                return routeParameters;
            },
            routeToState: function (routeParameters) {
                var productIndexName = algoliaConfig.indexName + '_products';
                var uiStateProductIndex = {}

                uiStateProductIndex['query'] = routeParameters.q == '__empty__' ? '' : routeParameters.q;
                if (algoliaConfig.isLandingPage && typeof uiStateProductIndex['query'] === 'undefined' && algoliaConfig.landingPage.query != '') {
                    uiStateProductIndex['query'] = algoliaConfig.landingPage.query;
                }

                var landingPageConfig = algoliaConfig.isLandingPage && algoliaConfig.landingPage.configuration ?
                    JSON.parse(algoliaConfig.landingPage.configuration) :
                    {};

                uiStateProductIndex['refinementList'] = {};
                uiStateProductIndex['hierarchicalMenu'] = {};
                uiStateProductIndex['range'] = {};
                if (algoliaConfig.facets) {
                    for (var i = 0; i < algoliaConfig.facets.length; i++) {
                        var currentFacet = algoliaConfig.facets[i];
                        // Handle refinement facets
                        if (currentFacet.attribute != 'categories' && (currentFacet.type == 'conjunctive' || currentFacet.type == 'disjunctive')) {
                            uiStateProductIndex['refinementList'][currentFacet.attribute] = routeParameters[currentFacet.attribute] && routeParameters[currentFacet.attribute].split('~');
                            if (algoliaConfig.isLandingPage &&
                                typeof uiStateProductIndex['refinementList'][currentFacet.attribute] === 'undefined' &&
                                currentFacet.attribute in landingPageConfig) {
                                uiStateProductIndex['refinementList'][currentFacet.attribute] = landingPageConfig[currentFacet.attribute].split('~');
                            }
                        }
                        // Handle categories facet
                        if (currentFacet.attribute == 'categories' && !algoliaConfig.isCategoryPage) {
                            uiStateProductIndex['hierarchicalMenu']['categories.level0'] = routeParameters['categories'] && routeParameters['categories'].split('~');
                            if (algoliaConfig.isLandingPage &&
                                typeof uiStateProductIndex['hierarchicalMenu']['categories.level0'] === 'undefined' &&
                                'categories.level0' in landingPageConfig) {
                                uiStateProductIndex['hierarchicalMenu']['categories.level0'] = landingPageConfig['categories.level0'].split(algoliaConfig.instant.categorySeparator);
                            }
                        }
                        if (currentFacet.attribute == 'categories' && algoliaConfig.isCategoryPage) {
                            uiStateProductIndex['hierarchicalMenu']['categories.level0'] = [algoliaConfig.request.path];
                        }
                        // Handle sliders
                        if (currentFacet.type == 'slider' || currentFacet.type == 'priceRanges') {
                            var currentFacetAttribute = currentFacet.attribute;
                            uiStateProductIndex['range'][currentFacetAttribute] = routeParameters[currentFacetAttribute] && routeParameters[currentFacetAttribute];
                            if (algoliaConfig.isLandingPage &&
                                typeof uiStateProductIndex['range'][currentFacetAttribute] === 'undefined' &&
                                currentFacetAttribute in landingPageConfig) {

                                var facetValue = '';
                                if (typeof landingPageConfig[currentFacetAttribute]['>='] !== "undefined") {
                                    facetValue = landingPageConfig[currentFacetAttribute]['>='][0];
                                }
                                facetValue += ':';
                                if (typeof landingPageConfig[currentFacetAttribute]['<='] !== "undefined") {
                                    facetValue += landingPageConfig[currentFacetAttribute]['<='][0];
                                }
                                uiStateProductIndex['range'][currentFacetAttribute] = facetValue;
                            }
                        }
                    }

                }
                uiStateProductIndex['sortBy'] = routeParameters.sortBy;
                uiStateProductIndex['page'] = routeParameters.page;

                var uiState = {};
                uiState[productIndexName] = uiStateProductIndex;
                return uiState;
            }
        }
    };

    const utils = {
        isMobileUserAgent: () => {
            const mobileRegex = /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini|mobile/i;
            return mobileRegex.test(navigator.userAgent);
        },
        
        isTouchDevice: () => {
            return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        }
    };
    
    const legacyGlobalFunctions = {
        isMobile: () => {
            return utils.isMobileUserAgent() || utils.isTouchDevice();
        },

        getCookie: (name) => {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            return (parts.length === 2) 
                ? parts.pop().split(';').shift() 
                : '';
        },

        // @deprecated This function will be removed from this module in a future version
        // This global function is highly specific to InstantSearch and has never been used for anything else
        // It will eventually be relocated to the algoliaInstantSearch lib
        transformHit: (hit, price_key, helper) => {
            if (Array.isArray(hit.categories))
                hit.categories = hit.categories.join(', ');
    
            if (hit._highlightResult.categories_without_path && Array.isArray(hit.categories_without_path)) {
                hit.categories_without_path = $.map(hit._highlightResult.categories_without_path, function (category) {
                    return category.value;
                });
    
                hit.categories_without_path = hit.categories_without_path.join(', ');
            }
    
            var matchedColors = [];
    
            if (helper && algoliaConfig.useAdaptiveImage === true) {
                if (hit.images_data && helper.state.facetsRefinements.color) {
                    matchedColors = helper.state.facetsRefinements.color.slice(0); // slice to clone
                }
    
                if (hit.images_data && helper.state.disjunctiveFacetsRefinements.color) {
                    matchedColors = helper.state.disjunctiveFacetsRefinements.color.slice(0); // slice to clone
                }
            }
    
            if (Array.isArray(hit.color)) {
                var colors = [];
    
                $.each(hit._highlightResult.color, function (i, color) {
                    if (color.matchLevel === undefined || color.matchLevel === 'none') {
                        return;
                    }
    
                    colors.push(color);
    
                    if (algoliaConfig.useAdaptiveImage === true) {
                        var matchedColor = color.matchedWords.join(' ');
                        if (hit.images_data && color.fullyHighlighted && color.fullyHighlighted === true) {
                            matchedColors.push(matchedColor);
                        }
                    }
                });
    
                hit._highlightResult.color = colors;
            } else {
                if (hit._highlightResult.color && hit._highlightResult.color.matchLevel === 'none') {
                    hit._highlightResult.color = {value: ''};
                }
            }
    
            if (algoliaConfig.useAdaptiveImage === true) {
                $.each(matchedColors, function (i, color) {
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
    
            if (Array.isArray(hit.price)) {
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
            }
    
            /* Added code to bind default bundle options for add to cart */
            if (hit.default_bundle_options) {
                var default_bundle_option = [];
                for (const property in hit.default_bundle_options) {
                    const optionsData = {
                        optionId: property,
                        selectionId : hit.default_bundle_options[property]
                    }
                    default_bundle_option.push(optionsData);
                }
                hit._highlightResult.default_bundle_options = default_bundle_option;
            }
    
            // Add to cart parameters
            var action = algoliaConfig.instant.addToCartParams.action + 'product/' + hit.objectID + '/';
    
            var correctFKey = this.getCookie('form_key');
    
            if (correctFKey != "" && algoliaConfig.instant.addToCartParams.formKey != correctFKey) {
                algoliaConfig.instant.addToCartParams.formKey = correctFKey;
            }
    
            hit.addToCart = {
                'action': action,
                'redirectUrlParam': algoliaConfig.instant.addToCartParams.redirectUrlParam,
                'uenc': algoliaBase64.mageEncode(action),
                'formKey': algoliaConfig.instant.addToCartParams.formKey
            };
    
            if (hit.__queryID) {
    
                hit.urlForInsights = hit.url;
    
                if (algoliaConfig.ccAnalytics.enabled
                    && algoliaConfig.ccAnalytics.conversionAnalyticsMode !== 'disabled') {
                    var insightsDataUrlString = $.param({
                        queryID: hit.__queryID,
                        objectID: hit.objectID,
                        indexName: hit.__indexName
                    });
                    if (hit.url.indexOf('?') > -1) {
                        hit.urlForInsights += '&' + insightsDataUrlString;
                    } else {
                        hit.urlForInsights += '?' + insightsDataUrlString;
                    }
                }
            }
    
            return hit;
        },

        /** @deprecated This function should no longer be used and will be removed in a future version */
        fixAutocompleteCssHeight: () => {
            if ($(document).width() > 768) {
                $(".other-sections").css('min-height', '0');
                $(".aa-dataset-products").css('min-height', '0');
                var height = Math.max($(".other-sections").outerHeight(), $(".aa-dataset-products").outerHeight());
                $(".aa-dataset-products").css('min-height', height);
            }
        },

        /** @deprecated This function should no longer be used and will be removed in a future version */
        fixAutocompleteCssSticky: (menu) => {
            var dropdown_menu = $('#algolia-autocomplete-container .aa-dropdown-menu');
            var autocomplete_container = $('#algolia-autocomplete-container');
            autocomplete_container.removeClass('reverse');
    
            /** Reset computation **/
            dropdown_menu.css('top', '0px');
    
            /** Stick menu vertically to the input **/
            var targetOffset = Math.round(menu.offset().top + menu.outerHeight());
            var currentOffset = Math.round(autocomplete_container.offset().top);
    
            dropdown_menu.css('top', (targetOffset - currentOffset) + 'px');
    
            if (menu.offset().left + menu.outerWidth() / 2 > $(document).width() / 2) {
                /** Stick menu horizontally align on right to the input **/
                dropdown_menu.css('right', '0px');
                dropdown_menu.css('left', 'auto');
    
                var targetOffset = Math.round(menu.offset().left + menu.outerWidth());
                var currentOffset = Math.round(autocomplete_container.offset().left + autocomplete_container.outerWidth());
    
                dropdown_menu.css('right', (currentOffset - targetOffset) + 'px');
            } else {
                /** Stick menu horizontally align on left to the input **/
                dropdown_menu.css('left', 'auto');
                dropdown_menu.css('right', '0px');
                autocomplete_container.addClass('reverse');
    
                var targetOffset = Math.round(menu.offset().left);
                var currentOffset = Math.round(autocomplete_container.offset().left);
    
                dropdown_menu.css('left', (targetOffset - currentOffset) + 'px');
            }
        },

        createISWidgetContainer: (attributeName) => {
            var div = document.createElement('div');
            div.className = 'is-widget-container-' + attributeName.split('.').join('_');
            div.dataset.attr = attributeName;
    
            return div;
        }
    };

    if (USE_GLOBALS) {
        Object.assign(
            window, 
            {
                algolia,
                routing
            },
            legacyGlobalFunctions
        );
    }

    // TODO move as mixable to autocomplete.js
    const handleAutoCompleteSubmit = (e) => {
        let query = $(this).find(algoliaConfig.autocomplete.selector).val();

        query = encodeURIComponent(query);

        if (algoliaConfig.instant.enabled && query === '')
            query = '__empty__';

        window.location = $(this).attr('action') + '?q=' + query;

        return false;
    };

    const initialize = () => {
        if (typeof algoliaConfig === 'undefined') {
            return;
        }
        $(algoliaConfig.autocomplete.selector).each(function () {
            $(this).closest('form').on('submit', handleAutoCompleteSubmit);
        });
    }


    $(function ($) {
        initialize();
    });

    return {
        ...algolia,
        routing,
        ...utils,
        ...legacyGlobalFunctions
    };

});
