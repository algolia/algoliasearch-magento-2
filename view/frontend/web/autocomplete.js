define(
    [
        'jquery',
        'algoliaBundle',
        'pagesHtml',
        'categoriesHtml',
        'productsHtml',
        'suggestionsHtml',
        'additionalHtml',
        'algoliaCommon',
        'algoliaInsights',
        'algoliaHooks',
        'domReady!'
    ],
    function ($, algoliaBundle, pagesHtml, categoriesHtml, productsHtml, suggestionsHtml, additionalHtml) {

        const DEFAULT_HITS_PER_SECTION = 2;
        const DEBOUNCE_MS = algoliaConfig.autocomplete.debounceMilliseconds;
        const MIN_SEARCH_LENGTH_CHARS = algoliaConfig.autocomplete.minimumCharacters;

        // global state
        let suggestionSection = false;
        let algoliaFooter;

        /** We have nothing to do here if autocomplete is disabled **/
        if (typeof algoliaConfig === 'undefined' || !algoliaConfig.autocomplete.enabled) {
            return;
        }

        /**
         * Initialise Algolia client
         * Docs: https://www.algolia.com/doc/api-client/getting-started/instantiate-client-index/
         **/
        const searchClient = algoliaBundle.algoliasearch(algoliaConfig.applicationId, algoliaConfig.apiKey);
        searchClient.addAlgoliaAgent('Magento2 integration (' + algoliaConfig.extensionVersion + ')');

        // autocomplete code moved from common.js to autocomplete.js
        const transformAutocompleteHit = function (hit, price_key, helper) {
            if (Array.isArray(hit.categories))
                hit.categories = hit.categories.join(', ');

            if (hit._highlightResult.categories_without_path && Array.isArray(hit.categories_without_path)) {
                hit.categories_without_path = $.map(hit._highlightResult.categories_without_path, function (category) {
                    return category.value;
                });

                hit.categories_without_path = hit.categories_without_path.join(', ');
            }

            let matchedColors = [];

            // TODO: Adapt this migrated code from common.js - helper not utilized
            if (helper && algoliaConfig.useAdaptiveImage === true) {
                if (hit.images_data && helper.state.facetsRefinements.color) {
                    matchedColors = helper.state.facetsRefinements.color.slice(0); // slice to clone
                }

                if (hit.images_data && helper.state.disjunctiveFacetsRefinements.color) {
                    matchedColors = helper.state.disjunctiveFacetsRefinements.color.slice(0); // slice to clone
                }
            }

            if (Array.isArray(hit.color)) {
                let colors = [];

                $.each(hit._highlightResult.color, function (i, color) {
                    if (color.matchLevel === undefined || color.matchLevel === 'none') {
                        return;
                    }

                    colors.push(color.value);

                    if (algoliaConfig.useAdaptiveImage === true) {
                        const matchedColor = color.matchedWords.join(' ');
                        if (hit.images_data && color.fullyHighlighted && color.fullyHighlighted === true) {
                            matchedColors.push(matchedColor);
                        }
                    }
                });

                colors = colors.join(', ');
                hit._highlightResult.color = {value: colors};
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
                    const priceExpiration = hit['price'][algoliaConfig.currencyCode]['special_to_date'];

                    if (algoliaConfig.now > priceExpiration + 1) {
                        hit['price'][algoliaConfig.currencyCode]['default_formated'] = hit['price'][algoliaConfig.currencyCode]['default_original_formated'];
                        hit['price'][algoliaConfig.currencyCode]['default_original_formated'] = false;
                    }
                }
            }

            // Add to cart parameters
            const action = algoliaConfig.instant.addToCartParams.action + 'product/' + hit.objectID + '/';

            const correctFKey = getCookie('form_key');

            if (correctFKey != "" && algoliaConfig.instant.addToCartParams.formKey != correctFKey) {
                algoliaConfig.instant.addToCartParams.formKey = correctFKey;
            }

            hit.addToCart = {
                'action':  action,
                'uenc':    AlgoliaBase64.mageEncode(action),
                'formKey': algoliaConfig.instant.addToCartParams.formKey
            };

            if (hit.__autocomplete_queryID) {

                hit.urlForInsights = hit.url;

                if (algoliaConfig.ccAnalytics.enabled
                    && algoliaConfig.ccAnalytics.conversionAnalyticsMode !== 'disabled') {
                    const insightsDataUrlString = $.param({
                        queryID:   hit.__autocomplete_queryID,
                        objectID:  hit.objectID,
                        indexName: hit.__autocomplete_indexName
                    });
                    if (hit.url.indexOf('?') > -1) {
                        hit.urlForInsights += insightsDataUrlString
                    } else {
                        hit.urlForInsights += '?' + insightsDataUrlString;
                    }
                }
            }

            return hit;
        };

        const getNavigatorUrl = function (url) {
            if (algoliaConfig.autocomplete.isNavigatorEnabled) {
                return url;
            }
        }

        /**
         * Build pre-baked sources
         * @param section
         * @param searchClient
         * @returns object representing a single source
         */
        const buildAutocompleteSource = function (section, searchClient) {
            let options = {
                hitsPerPage:    section.hitsPerPage || DEFAULT_HITS_PER_SECTION,
                analyticsTags:  'autocomplete',
                clickAnalytics: true,
                distinct:       true
            };

            const getItemUrl = ({item}) => {
                return getNavigatorUrl(item.url);
            };

            const transformResponse = ({results, hits}) => {
                const resDetail = results[0];

                return hits.map(res => {
                    return res.map((hit, i) => {
                        return {
                            ...hit,
                            query: resDetail.query,
                            position: i + 1
                        }
                    })
                });
            };

            const defaultSectionIndex = `${algoliaConfig.indexName}_${section.name}`;

            // Default values for source
            const source = {
                sourceId: section.name,
                options,
                getItemUrl,
                transformResponse,
                indexName: defaultSectionIndex
            };

            if (section.name === "products") {
                options.facets = ['categories.level0'];
                options.numericFilters = 'visibility_search=1';
                options.ruleContexts = ['magento_filters', '']; // Empty context to keep backward compatibility for already created rules in dashboard

                // Allow custom override
                options = algolia.triggerHooks('beforeAutocompleteProductSourceOptions', options); //DEPRECATED - retaining for backward compatibility
                source.options = algolia.triggerHooks('afterAutocompleteProductSourceOptions', options);

                source.templates = {
                        noResults({html}) {
                            return productsHtml.getNoResultHtml({html});
                        },
                        header({items, html}) {
                            return productsHtml.getHeaderHtml({items, html})
                        },
                        item({item, components, html}) {
                            if (suggestionSection) {
                                $('.aa-Panel').addClass('productColumn2');
                                $('.aa-Panel').removeClass('productColumn1');
                            } else {
                                $('.aa-Panel').removeClass('productColumn2');
                                $('.aa-Panel').addClass('productColumn1');
                            }
                            if (algoliaFooter && algoliaFooter !== undefined && algoliaFooter !== null && $('#algoliaFooter').length === 0) {
                                $('.aa-PanelLayout').append(algoliaFooter);
                            }
                            const _data = transformAutocompleteHit(item, algoliaConfig.priceKey);
                            return productsHtml.getItemHtml({item: _data, components, html});
                        },
                        footer({items, html}) {
                            const resultDetails = {};
                            if (items.length) {
                                const firstItem = items[0];
                                resultDetails.allDepartmentsUrl = algoliaConfig.resultPageUrl + '?q=' + encodeURIComponent(firstItem.query);
                                resultDetails.nbHits = firstItem.nbHits;

                                if (algoliaConfig.facets.find(facet => facet.attribute === 'categories')) {
                                    let allCategories = [];
                                    if (typeof firstItem.allCategories !== 'undefined') {
                                        allCategories = Object.keys(firstItem.allCategories).map(key => {
                                            const url = resultDetails.allDepartmentsUrl + '&categories=' + encodeURIComponent(key);
                                            return {
                                                name:  key,
                                                value: firstItem.allCategories[key],
                                                url
                                            };
                                        });
                                    }
                                    //reverse value sort apparently...
                                    allCategories.sort((a, b) => b.value - a.value);
                                    resultDetails.allCategories = allCategories.slice(0, 2);
                                }
                            }
                            return productsHtml.getFooterHtml({html, ...resultDetails});
                        }
                    };
                source.transformResponse = ({results, hits}) => {
                    const resDetail = results[0];
                    return hits.map(res => {
                        return res.map((hit, i) => {
                            return {
                                ...hit,
                                nbHits:        resDetail.nbHits,
                                allCategories: resDetail.facets['categories.level0'],
                                query:         resDetail.query,
                                position:      i + 1
                            }
                        })
                    });
                };
            } else if (section.name === "categories") {
                if (section.name === "categories" && algoliaConfig.showCatsNotIncludedInNavigation === false) {
                    options.numericFilters = 'include_in_menu=1';
                }
                source.templates = {
                        noResults({html}) {
                            return categoriesHtml.getNoResultHtml({html});
                        },
                        header({html, items}) {
                            return categoriesHtml.getHeaderHtml({section, html, items});
                        },
                        item({item, components, html}) {
                            return categoriesHtml.getItemHtml({item, components, html});
                        },
                        footer({html, items}) {
                            return categoriesHtml.getFooterHtml({section, html, items});
                        }
                    };
            } else if (section.name === "pages") {
                source.templates = {
                        noResults({html}) {
                            return pagesHtml.getNoResultHtml({html});
                        },
                        header({html, items}) {
                            return pagesHtml.getHeaderHtml({section, html, items});
                        },
                        item({item, components, html}) {
                            return pagesHtml.getItemHtml({item, components, html});
                        },
                        footer({html, items}) {
                            return pagesHtml.getFooterHtml({section, html, items});
                        }
                    };
            } else {
                /** If is not products, categories, pages or suggestions, it's additional section **/
                source.indexName = `${algoliaConfig.indexName}_section_${section.name}`;
                source.templates = {
                        noResults({html}) {
                            return additionalHtml.getNoResultHtml({html});
                        },
                        header({html, items}) {
                            return additionalHtml.getHeaderHtml({section, html, items});
                        },
                        item({item, components, html}) {
                            return additionalHtml.getItemHtml({item, components, html, section});
                        },
                        footer({html, items}) {
                            return additionalHtml.getFooterHtml({section, html, items});
                        }
                    };
            }

            return source;
        };

        const buildSuggestionsPlugin = function() {
            return algoliaBundle.createQuerySuggestionsPlugin.createQuerySuggestionsPlugin({
                searchClient,
                indexName: `${algoliaConfig.indexName}_suggestions`,
                getSearchParams() {
                    return {
                        hitsPerPage: algoliaConfig.autocomplete.nbOfQueriesSuggestions,
                        clickAnalytics: true
                    };
                },
                transformSource({source}) {
                    return {
                        ...source,
                        getItems({ query }) {
                          const items = filterMinChars(query, source.getItems());
                          const oldTransform = items.transformResponse;
                          items.transformResponse = arg => {
                              const hits = oldTransform ? oldTransform(arg) : arg.hits;
                              return hits.map((hit, i) => {
                                  return {
                                      ...hit,
                                      position: i + 1
                                  }
                              });
                          };
                          return items;
                        },
                        getItemUrl({item}) {
                            return getNavigatorUrl(algoliaConfig.resultPageUrl + `?q=${item.query}`);
                        },
                        templates: {
                            noResults({html}) {
                                return suggestionsHtml.getNoResultHtml({html});
                            },
                            header({html, items}) {
                                return suggestionsHtml.getHeaderHtml({html, items});
                            },
                            item({item, components, html}) {
                                return suggestionsHtml.getItemHtml({item, components, html})
                            },
                            footer({html, items}) {
                                return suggestionsHtml.getFooterHtml({html, items})
                            },
                        },
                    };
                },
            });
        };

        const filterMinChars = (query, result) => {
            return (query.length >= MIN_SEARCH_LENGTH_CHARS)
                ? result
                : [];
        }

        const debouncePromise = (fn, time) => {
            let timerId = undefined;

            return function debounced(...args) {
                if (timerId) {
                    clearTimeout(timerId);
                }

                return new Promise((resolve) => {
                    timerId = setTimeout(() => resolve(fn(...args)), time);
                });
            };
        };
        const debounced = debouncePromise((items) => Promise.resolve(items), DEBOUNCE_MS);

        /**
         * Load suggestions, products and categories as configured
         * NOTE: Sequence matters!
         * **/
        if (algoliaConfig.autocomplete.nbOfCategoriesSuggestions > 0) {
            algoliaConfig.autocomplete.sections.unshift({
                hitsPerPage: algoliaConfig.autocomplete.nbOfCategoriesSuggestions,
                label:       algoliaConfig.translations.categories,
                name:        "categories"
            });
        }

        if (algoliaConfig.autocomplete.nbOfProductsSuggestions > 0) {
            algoliaConfig.autocomplete.sections.unshift({
                hitsPerPage: algoliaConfig.autocomplete.nbOfProductsSuggestions,
                label:       algoliaConfig.translations.products,
                name:        "products"
            });
        }

        /** Setup autocomplete data sources **/
        let sources = algoliaConfig.autocomplete.sections.map(section => buildAutocompleteSource(section, searchClient));
        sources = algolia.triggerHooks('beforeAutocompleteSources', sources, searchClient); // DEPRECATED
        sources = algolia.triggerHooks('afterAutocompleteSources', sources, searchClient);

        let plugins = [];

        if (algoliaConfig.autocomplete.nbOfQueriesSuggestions > 0) {
            suggestionSection = true; //relies on global - needs refactor
            plugins.push(buildSuggestionsPlugin());
        }
        plugins = algolia.triggerHooks('afterAutocompletePlugins', plugins, searchClient);

        /**
         * Setup the autocomplete search input
         * For autocomplete feature is used Algolia's autocomplete.js library
         * Docs: https://github.com/algolia/autocomplete.js
         **/

        let autocompleteConfig = [];
        let options = algolia.triggerHooks('beforeAutocompleteOptions', {}); //DEPRECATED

        options = {
            ...options,
            container:          algoliaConfig.autocomplete.selector,
            placeholder:        algoliaConfig.translations.placeholder,
            debug:              algoliaConfig.autocomplete.isDebugEnabled,
            detachedMediaQuery: 'none',
            onSubmit(data) {
                if (data.state.query && data.state.query !== null && data.state.query !== "") {
                    window.location.href = algoliaConfig.resultPageUrl + `?q=${encodeURIComponent(data.state.query)}`;
                }
            },
            getSources({query}) {
              return filterMinChars(query, debounced(autocompleteConfig));
            },
            shouldPanelOpen({ state }) {
                return state.query.length >= MIN_SEARCH_LENGTH_CHARS;
            }
        };

        if (isMobile() === true) {
            // Set debug to true, to be able to remove keyboard and be able to scroll in autocomplete menu
            options.debug = true;
        }

        if (algoliaConfig.removeBranding === false) {
            algoliaFooter = `<div id="algoliaFooter" class="footer_algolia"><span class="algolia-search-by-label">${algoliaConfig.translations.searchBy}</span><a href="https://www.algolia.com/?utm_source=magento&utm_medium=link&utm_campaign=magento_autocompletion_menu" title="${algoliaConfig.translations.searchBy} Algolia" target="_blank"><img src="${algoliaConfig.urls.logo}" alt="${algoliaConfig.translations.searchBy} Algolia" /></a></div>`;
        }

        // Keep for backward compatibility
        if (typeof algoliaHookBeforeAutocompleteStart === 'function') {
            console.warn('Deprecated! You are using an old API for Algolia\'s front end hooks. ' +
                'Please, replace your hook method with new hook API. ' +
                'More information you can find on https://www.algolia.com/doc/integration/magento-2/customize/custom-front-end-events/');

            const hookResult = algoliaHookBeforeAutocompleteStart(sources, options, searchClient);

            sources = hookResult.shift();
            options = hookResult.shift();
        }

        sources.forEach(data => {
            if (!data.sourceId) {
                console.error("Algolia Autocomplete: sourceId is required for custom sources");
                return;
            }
            const getItems = ({query}) => {
                return algoliaBundle.getAlgoliaResults({
                    searchClient,
                    queries: [
                        {
                            query,
                            indexName: data.indexName,
                            params:    data.options,
                        },
                    ],
                    // only set transformResponse if defined (necessary check for custom sources)
                    ...(data.transformResponse && { transformResponse : data.transformResponse })
                });
            };
            const fallbackTemplates = {
                noResults: () => 'No results',
                header: () => data.sourceId,
                item: ({item}) => {
                    console.error(`Algolia Autocomplete: No template defined for source "${data.sourceId}"`);
                    return '[ITEM TEMPLATE MISSING]';
                }
            };
            autocompleteConfig.push({
                sourceId: data.sourceId,
                getItems,
                templates: { ...fallbackTemplates, ...(data.templates || {}) },
                // only set getItemUrl if defined (necessary check for custom sources)
                ...(data.getItemUrl && { getItemUrl: data.getItemUrl })
            });
        });
        options.plugins = plugins;

        options = algolia.triggerHooks('afterAutocompleteOptions', options);

        /** Bind autocomplete feature to the input */
        let algoliaAutocompleteInstance = algoliaBundle.autocomplete(options);
        algoliaAutocompleteInstance = algolia.triggerHooks('afterAutocompleteStart', algoliaAutocompleteInstance);

        //Autocomplete insight click conversion
        if (algoliaConfig.ccAnalytics.enabled) {
            $(document).on('click', '.algoliasearch-autocomplete-hit', function () {
                const $this = $(this);
                if ($this.data('clicked')) return;

                const objectId = $this.attr('data-objectId');
                const indexName = $this.attr('data-index');
                const queryId = $this.attr('data-queryId');
                const position = $this.attr('data-position');

                const eventData = algoliaInsights.buildEventData(
                    'Clicked', objectId, indexName, position, queryId
                );
                algoliaInsights.trackClick(eventData);
                $this.attr('data-clicked', true);
            });
        }

        if (algoliaConfig.autocomplete.isNavigatorEnabled) {
            $("body").append('<style>.aa-Item[aria-selected="true"]{background-color: #f2f2f2;}</style>');
        }


    }
);
