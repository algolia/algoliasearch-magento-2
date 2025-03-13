define([
    'uiComponent',
    'jquery',

    // Algolia core UI libs
    'algoliaSearchLib',
    'algoliaInstantSearchLib',

    // Algolia integration dependencies
    'algoliaCommon',
    'algoliaBase64',
    'algoliaTemplateEngine',

    // Magento core libs
    'Magento_Catalog/js/price-utils',

    'algoliaInsights',
    'algoliaHooks',
], function (Component, $, algoliasearch, instantsearch, algoliaCommon, algoliaBase64, templateEngine, priceUtils) {

    return Component.extend({
        initialize(_config, _element) {
            this.buildInstantSearch().then(() => console.log("[Algolia] InstantSearch build complete"));
        },

        isStarted: false,

        minQuerySuggestions: 4,

        /**
         * Initialize search results using Algolia's InstantSearch.js library v4
         * Docs: https://www.algolia.com/doc/api-reference/widgets/instantsearch/js/
         */
        async buildInstantSearch() {
            if (!this.checkInstantSearchEnablement()) return;

            const templateProcessor = await templateEngine.getSelectedEngineAdapter();

            const mockAlgoliaBundle = this.mockAlgoliaBundle();

            this.invokeLegacyHooks();

            this.setupWrapper(templateProcessor);

            const search = instantsearch(this.getInstantSearchOptions(mockAlgoliaBundle));

            search.client.addAlgoliaAgent(this.getAlgoliaAgent());

            this.prepareSortingIndices();

            this.initializeWidgets(
                search,
                this.getAllWidgetConfiguration(search, templateProcessor),
                mockAlgoliaBundle
            );

            // TODO: Refactor
            // Capture active redirect URL with IS facet params for add to cart from PLP
            if (algoliaConfig.instant.isAddToCartEnabled) {
                search.on('render', () => {
                    const cartForms = document.querySelectorAll(
                        '[data-role="tocart-form"]'
                    );
                    cartForms.forEach((form, i) => {
                        form.addEventListener('submit', (e) => {
                            const url = `${algoliaConfig.request.url}${window.location.search}`;
                            e.target.elements[
                                algoliaConfig.instant.addToCartParams.redirectUrlParam
                                ].value = algoliaBase64.mageEncode(url);
                        });
                    });
                });
            }

            this.startInstantSearch(search, mockAlgoliaBundle);

            this.addMobileRefinementsToggle();
        },

        /**
         * Builds the allWidgetConfiguration that is used to define the widgets added to InstantSearch
         * This object is also passed to the `beforeWidgetInitialization` hook
         * See https://www.algolia.com/doc/integration/magento-2/customize/custom-front-end-events/#instantsearch-page-events
         *
         * @param search
         * @param templateProcessor
         * @returns {Object<string, Object>}
         */
        getAllWidgetConfiguration(search, templateProcessor) {
            let allWidgetConfiguration = {
                configure   : this.getSearchParameters(),
                custom      : this.getCustomWidgets(),
                stats: this.getStats(templateProcessor),
                sortBy: this.getSortBy(),
                queryRuleCustomData: this.getQueryRuleCustomData(),
            };

            if (algoliaConfig.instant.isSearchBoxEnabled) {
                allWidgetConfiguration.searchBox = this.getSearchBox()
            }

            allWidgetConfiguration = this.initializeHits(allWidgetConfiguration, search);

            allWidgetConfiguration = this.initializeRefinements(allWidgetConfiguration);

            allWidgetConfiguration = this.initializeFacets(allWidgetConfiguration);

            // TODO: Refactor
            if (algoliaConfig.analytics.enabled) {
                if (typeof algoliaAnalyticsPushFunction !== 'function') {
                    var algoliaAnalyticsPushFunction = function (
                        formattedParameters,
                        state,
                        results
                    ) {
                        var trackedUrl =
                            '/catalogsearch/result/?q=' +
                            state.query +
                            '&' +
                            formattedParameters +
                            '&numberOfHits=' +
                            results.nbHits;

                        // Universal Analytics
                        if (typeof window.ga !== 'undefined') {
                            window.ga('set', 'page', trackedUrl);
                            window.ga('send', 'pageView');
                        }
                    };
                }

                allWidgetConfiguration['analytics'] = {
                    pushFunction          : algoliaAnalyticsPushFunction,
                    delay                 : algoliaConfig.analytics.delay,
                    triggerOnUIInteraction: algoliaConfig.analytics.triggerOnUiInteraction,
                    pushInitialSearch     : algoliaConfig.analytics.pushInitialSearch,
                };
            }
            return allWidgetConfiguration;
        },

        /**
         * Add all facet widgets to allWidgetConfiguration
         *
         * @param allWidgetConfiguration
         * @returns {*}
         */
        initializeFacets(allWidgetConfiguration) {
            const customAttributeFacets = this.getCustomAttributeFacets();

            const wrapper = document.getElementById('instant-search-facets-container');
            algoliaConfig.facets.forEach(
                facet => {
                    facet.wrapper = wrapper;

                    if (facet.attribute.includes('price')) {
                        facet.attribute += algoliaConfig.priceKey;
                    }

                    const templates = {
                        item: this.getTemplateContentsFromDOM('#refinements-lists-item-template')
                    };

                    const widgetInfo = customAttributeFacets[facet.attribute]?.(facet, templates)
                        ?? this.getFacetWidget(facet, templates);

                    const [widgetType, widgetConfig] = widgetInfo;

                    if (allWidgetConfiguration.hasOwnProperty(widgetType)) {
                        allWidgetConfiguration[widgetType].push(widgetConfig);
                    } else {
                        allWidgetConfiguration[widgetType] = [widgetConfig];
                    }
                }
            );

            return allWidgetConfiguration;
        },

        /**
         * Here are specified custom attributes widgets which require special code to run properly
         * Custom widgets can be added to this object like [attribute]: function(facet, templates)
         * Function must return an array [<widget name>: string, <widget options>: object]
         * (Same as getFacetWidget() which handles generic facets)
         *
         * @returns {Object<string, function>}
         */
        getCustomAttributeFacets() {
            return {
                categories: this.getCategoriesFacet()
            };
        },

        /**
         * Get custom attribute function to generate params for categories hierarchicalMenu widget
         */
        getCategoriesFacet() {
            return (facet, templates) => {
                const hierarchical_levels = [];
                for (let l = 0; l < 10; l++) {
                    hierarchical_levels.push('categories.level' + l.toString());
                }

                const hierarchicalMenuParams = {
                    container      : facet.wrapper.appendChild(
                        algoliaCommon.createISWidgetContainer(facet.attribute)
                    ),
                    attributes     : hierarchical_levels,
                    separator      : algoliaConfig.instant.categorySeparator,
                    templates      : templates,
                    showParentLevel: true,
                    limit          : algoliaConfig.maxValuesPerFacet,
                    sortBy         : ['name:asc'],
                    transformItems(items) {
                        return algoliaConfig.isCategoryPage
                            ? items.map((item) => {
                                return {
                                    ...item,
                                    categoryUrl: algoliaConfig.instant
                                        .isCategoryNavigationEnabled
                                        ? algoliaConfig.request.childCategories[item.value]['url']
                                        : '',
                                };
                            })
                            : items;
                    },
                };

                if (algoliaConfig.isCategoryPage) {
                    hierarchicalMenuParams.rootPath = algoliaConfig.request.path;
                }

                hierarchicalMenuParams.templates.item =
                    '<a class="{{cssClasses.link}} {{#isRefined}}{{cssClasses.link}}--selected{{/isRefined}}" href="{{categoryUrl}}"><span class="{{cssClasses.label}}">{{label}}</span>' +
                    ' ' +
                    '<span class="{{cssClasses.count}}">{{#helpers.formatNumber}}{{count}}{{/helpers.formatNumber}}</span>' +
                    '</a>';
                hierarchicalMenuParams.panelOptions = {
                    templates: {
                        header:
                            '<div class="name">' +
                            (facet.label ? facet.label : facet.attribute) +
                            '</div>',
                    },
                    hidden   : function ({items}) {
                        return !items.length;
                    },
                };

                return ['hierarchicalMenu', hierarchicalMenuParams];
            };
        },

        /**
         * Return an array of custom widgets
         * Docs: https://www.algolia.com/doc/guides/building-search-ui/widgets/create-your-own-widgets/js/
         *
         * @returns {({init(*): void, getWidgetSearchParameters(*): *, render(*): void})[]}
         */
        getCustomWidgets() {
            const customWidgets = [ this.getInitializeResultsWidget() ];
            if (algoliaConfig.showSuggestionsOnNoResultsPage) {
                customWidgets.push(this.getSuggestionsWidget(this.minQuerySuggestions));
            }
            return customWidgets;
        },

        /**
         * Custom widget - this widget is used to refine results for search page or catalog page
         * Docs: https://www.algolia.com/doc/guides/building-search-ui/widgets/create-your-own-widgets/js/
         *
         * @returns {{init(*): void, getWidgetSearchParameters(*): (*), render(*): void}|*}
         */
        getInitializeResultsWidget() {
            return {
                getWidgetSearchParameters(searchParameters) {
                    if (
                        algoliaConfig.request.query.length > 0 &&
                        location.hash.length < 1
                    ) {
                        return searchParameters.setQuery(
                            algoliaCommon.htmlspecialcharsDecode(algoliaConfig.request.query)
                        );
                    }
                    return searchParameters;
                },
                init(data) {
                    const page = data.helper.state.page;

                    if (algoliaConfig.request.refinementKey.length > 0) {
                        data.helper.toggleRefine(
                            algoliaConfig.request.refinementKey,
                            algoliaConfig.request.refinementValue
                        );
                    }

                    if (algoliaConfig.isCategoryPage) {
                        data.helper.addNumericRefinement('visibility_catalog', '=', 1);
                    } else {
                        data.helper.addNumericRefinement('visibility_search', '=', 1);
                    }

                    data.helper.setPage(page);
                }
            };
        },

        /**
         * Custom widget - Suggestions
         * This widget renders suggestion queries which might be interesting for your customer
         * Docs: https://www.algolia.com/doc/guides/building-search-ui/widgets/create-your-own-widgets/js/
         *
         * @param {number} minQuerySuggestions - postive integer for number of suggestions to display
         * @returns {{init(): void, suggestions: *[], render(*): void}}
         */
        getSuggestionsWidget(minQuerySuggestions) {
            return {
                suggestions: [],
                init() {
                    algoliaConfig.popularQueries.slice(
                        0,
                        Math.min(minQuerySuggestions, algoliaConfig.popularQueries.length)
                    ).forEach(
                        (query) => {
                            query = algoliaCommon.htmlspecialcharsEncode(query);
                            this.suggestions.push(
                                `<a href="${algoliaConfig.baseUrl}/catalogsearch/result/?q=${encodeURIComponent(query)}">${query}</a>`
                            );
                        }
                    );
                },
                render(data) {
                    let content = '';
                    if (data.results.hits.length === 0) {
                        const query = algoliaCommon.htmlspecialcharsEncode(data.results.query);
                        content = `<div class="no-results">`;
                        content += `<div><strong>${algoliaConfig.translations.noProducts} "${query}"</strong></div>`;
                        content += `<div class="popular-searches">`;
                        content += `<div>${algoliaConfig.translations.popularQueries}</div>`;
                        content += this.suggestions.join(', ');
                        content += `</div>`;
                        content += algoliaConfig.translations.or;
                        content += `<a href="${algoliaConfig.baseUrl}/catalogsearch/result/?q=__empty__">${algoliaConfig.translations.seeAll}</a>`;
                        content += `</div>`;
                    }
                    document.querySelector('#instant-empty-results-container').innerHTML = content;
                },
            };
        },

        /**
         * stats
         * Docs: https://www.algolia.com/doc/api-reference/widgets/stats/js/
         *
         * @param templateProcessor
         * @returns {{container: string, templates: {text: (function(*): *)}}}
         */
        getStats(templateProcessor) {
            return {
                container: '#algolia-stats',
                templates: {
                    text: (data) => {
                        data.first = data.page * data.hitsPerPage + 1;
                        data.last = Math.min(
                            data.page * data.hitsPerPage + data.hitsPerPage,
                            data.nbHits
                        );
                        data.seconds = data.processingTimeMS / 1000;
                        data.translations = window.algoliaConfig.translations;

                        const template = this.getTemplateContentsFromDOM('#instant-stats-template');
                        return templateProcessor.process(template, data);
                    },
                },
            }
        },

        /**
         * sortBy
         * Docs: https://www.algolia.com/doc/api-reference/widgets/sort-by/js/
         *
         * @returns {{container: string, items: *}}
         */
        getSortBy() {
            return {
                container: '#algolia-sorts',
                items    : algoliaConfig.sortingIndices.map((sortingIndice) => {
                    return {
                        label: sortingIndice.label,
                        value: sortingIndice.name,
                    };
                }),
            };
        },

        /**
         * queryRuleCustomData
         * The queryRuleCustomData widget displays custom data from Query Rules.
         * Docs: https://www.algolia.com/doc/api-reference/widgets/query-rule-custom-data/js/
         *
         * @returns {{container: string, templates: {default: string}}}
         */
        getQueryRuleCustomData() {
            return {
                container: '#algolia-banner',
                templates: {
                    default: '{{#items}} {{#banner}} {{{banner}}} {{/banner}} {{/items}}',
                },
            };
        },

        /**
         * @param allWidgetConfiguration
         * @returns {*}
         */
        initializeRefinements(allWidgetConfiguration) {
            const currentRefinementsAttributes = this.getCurrentRefinementsAttributes();
            allWidgetConfiguration.currentRefinements = this.getCurrentRefinements(currentRefinementsAttributes);
            allWidgetConfiguration.clearRefinements = this.getClearRefinements(currentRefinementsAttributes);
            return allWidgetConfiguration;
        },

        /**
         * currentRefinements
         * Widget displays all filters and refinements applied on query. It also let your customer to clear them one by one
         * Docs: https://www.algolia.com/doc/api-reference/widgets/current-refinements/js/
         *
         * @param currentRefinementsAttributes
         * @returns {{container: string, transformItems: (function(*): *), includedAttributes: *}}
         */
        getCurrentRefinements (currentRefinementsAttributes) {
            return {
                container: '#current-refinements',
                includedAttributes: currentRefinementsAttributes.map((attribute) => {
                    if (
                        attribute.name.indexOf('categories') === -1 ||
                        !algoliaConfig.isCategoryPage
                    )
                        // For category browse, requires a custom renderer to prevent removal of the root node from hierarchicalMenu widget
                        return attribute.name;
                }),

                transformItems: (items) => {
                    return (
                        items
                            // This filter is only applicable if categories facet is included as an attribute
                            .filter((item) => {
                                return (
                                    !algoliaConfig.isCategoryPage ||
                                    item.refinements.filter(
                                        (refinement) =>
                                            refinement.value !== algoliaConfig.request.path
                                    ).length
                                ); // do not expose the category root
                            })
                            .map((item) => {
                                const attribute = currentRefinementsAttributes.filter((_attribute) => {
                                    return item.attribute === _attribute.name;
                                })[0];
                                if (!attribute) return item;
                                item.label = attribute.label;
                                item.refinements.forEach(function (refinement) {
                                    if (refinement.type !== 'hierarchical') return refinement;

                                    const levels = refinement.label.split(
                                        algoliaConfig.instant.categorySeparator
                                    );
                                    const lastLevel = levels[levels.length - 1];
                                    refinement.label = lastLevel;
                                });
                                return item;
                            })
                    );
                },
            };
        },

        /**
         * clearRefinements
         * Widget displays a button that lets the user clean every refinement applied to the search. You can control which attributes are impacted by the button with the options.
         * Docs: https://www.algolia.com/doc/api-reference/widgets/clear-refinements/js/
         *
         * @param currentRefinementsAttributes
         * @returns {{container: string, cssClasses: {button: string[]}, transformItems: (function(*): *), templates: {resetLabel: (string|*)}, includedAttributes: *}}
         */
        getClearRefinements(currentRefinementsAttributes) {
            return {
                container         : '#clear-refinements',
                templates         : {
                    resetLabel: algoliaConfig.translations.clearAll,
                },
                includedAttributes: currentRefinementsAttributes.map(function (attribute) {
                    if (
                        !(
                            algoliaConfig.isCategoryPage &&
                            attribute.name.indexOf('categories') > -1
                        )
                    ) {
                        return attribute.name;
                    }
                }),
                cssClasses        : {
                    button: ['action', 'primary'],
                },
                transformItems    : function (items) {
                    return items.map(function (item) {
                        const attribute = currentRefinementsAttributes.filter(function (_attribute) {
                            return item.attribute === _attribute.name;
                        })[0];
                        if (!attribute) return item;
                        item.label = attribute.label;
                        return item;
                    });
                },
            };
        },

        /**
         * Setup hits and pagination based on configuration
         *
         * @param allWidgetConfiguration
         * @param search
         * @returns {*}
         */
        initializeHits(allWidgetConfiguration, search) {
            if (algoliaConfig.instant.infiniteScrollEnabled) {
                allWidgetConfiguration.infiniteHits = this.getInfiniteHits(search);
            } else {
                allWidgetConfiguration.hits = this.getHits(search);
                allWidgetConfiguration.pagination = this.getPagination();
            }
            return allWidgetConfiguration;
        },

        /**
         * hits
         * This widget renders products into result page as paginated hits
         * Docs: https://www.algolia.com/doc/api-reference/widgets/hits/js/
         *
         * @param search
         * @returns {{container: string, transformItems: (function(*, {results: *}): *), templates: {item: string, empty: string}}}
         */
        getHits(search) {
            return {
                container     : '#instant-search-results-container',
                templates     : {
                    empty: '<div></div>',
                    item : this.getTemplateContentsFromDOM('#instant-hit-template')
                },
                transformItems: function (items, {results}) {
                    if (algoliaConfig.instant.hidePagination) {
                        document.getElementById(
                            'instant-search-pagination-container'
                        ).style.display = results.nbPages <= 1 ? 'none' : 'block';
                    }

                    return items.map(function (item) {
                        item.__indexName = search.helper.lastResults.index;
                        item = algoliaCommon.transformHit(item, algoliaConfig.priceKey, search.helper);
                        item.isAddToCartEnabled = algoliaConfig.instant.isAddToCartEnabled;
                        item.algoliaConfig = window.algoliaConfig;
                        return item;
                    });
                },
            };
        },

        /**
         * pagination
         * Docs: https://www.algolia.com/doc/api-reference/widgets/pagination/js/
         *
         * @returns {{container: string, templates: {next: string, previous: string, totalPages: number, showLast: boolean, showFirst: boolean, showNext: boolean, showPrevious: boolean}}}
         */
        getPagination() {
            return {
                container   : '#instant-search-pagination-container',
                showFirst   : false,
                showLast    : false,
                showNext    : true,
                showPrevious: true,
                totalPages  : 1000,
                templates   : {
                    previous: algoliaConfig.translations.previousPage,
                    next    : algoliaConfig.translations.nextPage,
                },
            }
        },

        /**
         * infiniteHits
         * This widget renders products into result page as infinite scrolling hits
         * Docs: https://www.algolia.com/doc/api-reference/widgets/infinite-hits/js/
         *
         * @param search
         * @returns {{container: string, cssClasses: {loadPrevious: string[], loadMore: string[]}, transformItems: (function(*): *), templates: {item: string, showMoreText: string, empty: string}, escapeHits: boolean, showPrevious: boolean}}
         */
        getInfiniteHits(search) {
            return {
                container     : '#instant-search-results-container',
                templates     : {
                    empty       : '<div></div>',
                    item        : this.getTemplateContentsFromDOM('#instant-hit-template'),
                    showMoreText: algoliaConfig.translations.showMore,
                },
                cssClasses    : {
                    loadPrevious: ['action', 'primary'],
                    loadMore    : ['action', 'primary'],
                },
                transformItems: function (items) {
                    return items.map(function (item) {
                        item.__indexName = search.helper.lastResults.index;
                        item = algoliaCommon.transformHit(item, algoliaConfig.priceKey, search.helper);
                        item.isAddToCartEnabled = algoliaConfig.instant.isAddToCartEnabled;
                        return item;
                    });
                },
                showPrevious  : true,
                escapeHits    : true,
            };
        },

        /**
         * searchBox
         * Docs: https://www.algolia.com/doc/api-reference/widgets/search-box/js/
         *
         * @returns {{container: string, showSubmit: boolean, placeholder: *, queryHook: (function(*, *): *)}}
         */
        getSearchBox() {
            return {
                container  : '#instant-search-bar',
                placeholder: algoliaConfig.translations.searchFor,
                showSubmit : false,
                queryHook  : (inputValue, search) => {
                    if (
                        algoliaConfig.isSearchPage &&
                        !algoliaConfig.request.categoryId &&
                        !algoliaConfig.request.landingPageId.length
                    ) {
                        $('.page-title-wrapper span.base').html(
                            algoliaConfig.translations.searchTitle +
                            ": '" +
                            algoliaCommon.htmlspecialcharsEncode(inputValue) +
                            "'"
                        );
                    }
                    return search(inputValue);
                },
            };
        },

        initializeWidgets(search, allWidgetConfiguration, mockAlgoliaBundle) {
            allWidgetConfiguration = algoliaCommon.triggerHooks(
                'beforeWidgetInitialization',
                allWidgetConfiguration,
                mockAlgoliaBundle
            );

            Object.entries(allWidgetConfiguration).forEach(([widgetType, widgetConfig]) => {
                if (Array.isArray(widgetConfig)) {
                    for (const subWidgetConfig of widgetConfig) {
                        this.addWidget(search, widgetType, subWidgetConfig);
                    }
                } else {
                    this.addWidget(search, widgetType, widgetConfig);
                }
            });
        },

        getProductIndexName() {
            return algoliaConfig.indexName + '_products';
        },

        /**
         * @param mockAlgoliaBundle to be removed in a future release
         * @returns {*}
         */
        getInstantSearchOptions(mockAlgoliaBundle = {}) {
            return algoliaCommon.triggerHooks(
                'beforeInstantsearchInit',
                {
                    searchClient: algoliasearch(algoliaConfig.applicationId, algoliaConfig.apiKey),
                    indexName   : this.getProductIndexName(),
                    routing     : algoliaCommon.routing,
                },
                mockAlgoliaBundle
            );
        },

        prepareSortingIndices() {
            algoliaConfig.sortingIndices.unshift({
                name : this.getProductIndexName(),
                label: algoliaConfig.translations.relevance,
            });
        },

        /**
         * @deprecated - these hooks will be removed in a future version
         */
        invokeLegacyHooks() {
            if (typeof algoliaHookBeforeInstantsearchInit === 'function') {
                algoliaCommon.registerHook(
                    'beforeInstantsearchInit',
                    algoliaHookBeforeInstantsearchInit
                );
            }

            if (typeof algoliaHookBeforeWidgetInitialization === 'function') {
                algoliaCommon.registerHook(
                    'beforeWidgetInitialization',
                    algoliaHookBeforeWidgetInitialization
                );
            }

            if (typeof algoliaHookBeforeInstantsearchStart === 'function') {
                algoliaCommon.registerHook(
                    'beforeInstantsearchStart',
                    algoliaHookBeforeInstantsearchStart
                );
            }

            if (typeof algoliaHookAfterInstantsearchStart === 'function') {
                algoliaCommon.registerHook(
                    'afterInstantsearchStart',
                    algoliaHookAfterInstantsearchStart
                );
            }
        },

        /**
         * Pre-flight checks
         *
         * @returns {boolean} Returns true if InstantSearch is good to go
         */
        checkInstantSearchEnablement() {
            if (
                typeof algoliaConfig === 'undefined' ||
                !algoliaConfig.instant.enabled ||
                !algoliaConfig.isSearchPage
            ) {
                return false;
            }

            if (!$(algoliaConfig.instant.selector).length) {
                throw new Error(
                    `[Algolia] Invalid instant-search selector: ${algoliaConfig.instant.selector}`
                );
            }

            if (
                algoliaConfig.autocomplete.enabled &&
                $(algoliaConfig.instant.selector).find(
                    algoliaConfig.autocomplete.selector
                ).length
            ) {
                throw new Error(
                    `[Algolia] You can't have a search input matching "${algoliaConfig.autocomplete.selector}" ` +
                    `inside your instant selector "${algoliaConfig.instant.selector}"`
                );
            }

            return true;
        },

        /**
         * Handle nested Autocomplete (legacy)
         * @returns {boolean}
         */
        findAutocomplete() {
            if (algoliaConfig.autocomplete.enabled) {
                const $nestedAC = $(algoliaConfig.instant.selector).find('#algolia-autocomplete-container');
                if ($nestedAC.length) {
                    $nestedAC.remove();
                    return true;
                }
            }
            return false;
        },

        /**
         * Build wrapper DOM object to contain InstantSearch
         * @param templateProcessor
         */
        setupWrapper(templateProcessor) {
            const div = document.createElement('div');
            $(div).addClass('algolia-instant-results-wrapper');

            $(algoliaConfig.instant.selector).addClass(
                'algolia-instant-replaced-content'
            );
            $(algoliaConfig.instant.selector).wrap(div);

            $('.algolia-instant-results-wrapper').append(
                '<div class="algolia-instant-selector-results"></div>'
            );

            const template = this.getTemplateContentsFromDOM('#instant_wrapper_template');
            const templateVars = {
                second_bar      : algoliaConfig.instant.enabled,
                findAutocomplete: this.findAutocomplete(),
                config          : algoliaConfig.instant,
                translations    : algoliaConfig.translations,
            };

            const wrapperHtml = templateProcessor.process(template, templateVars);
            $('.algolia-instant-selector-results').html(wrapperHtml).show();
        },

        /**
         * @param selector
         * @returns {string}
         */
        getTemplateContentsFromDOM(selector) {
            const element = document.querySelector(selector);
            if (element) return element.innerHTML;

            throw new Error(`[Algolia] Invalid template selector: ${selector}`);
        },

        /**
         * @returns {string[]}
         */
        getRuleContexts() {
            const ruleContexts = ['magento_filters', '']; // Empty context to keep BC for already create rules in dashboard
            if (algoliaConfig.request.categoryId.length) {
                ruleContexts.push('magento-category-' + algoliaConfig.request.categoryId);
            }

            if (algoliaConfig.request.landingPageId.length) {
                ruleContexts.push(
                    'magento-landingpage-' + algoliaConfig.request.landingPageId
                );
            }
            return ruleContexts;
        },

        /**
         * Get raw search parameters for configure widget
         * See https://www.algolia.com/doc/api-reference/widgets/configure/js/
         * @returns {*[]}
         */
        getSearchParameters() {
            const searchParameters = {
                hitsPerPage : algoliaConfig.hitsPerPage,
                ruleContexts: this.getRuleContexts()
            };

            if (
                algoliaConfig.request.path.length &&
                window.location.hash.indexOf('categories.level0') === -1
            ) {
                if (!algoliaConfig.areCategoriesInFacets) {
                    searchParameters['facetsRefinements'] = {};
                    searchParameters['facetsRefinements'][
                        'categories.level' + algoliaConfig.request.level
                    ] = [algoliaConfig.request.path];
                }
            }

            if (
                algoliaConfig.instant.isVisualMerchEnabled &&
                algoliaConfig.isCategoryPage
            ) {
                searchParameters.filters = `${
                    algoliaConfig.instant.categoryPageIdAttribute
                }:"${algoliaConfig.request.path.replace(/"/g, '\\"')}"`;
            }

            return searchParameters;
        },

        /**
         * @returns {string}
         */
        getAlgoliaAgent() {
            return 'Magento2 integration (' + algoliaConfig.extensionVersion + ')';
        },

        /**
         * Setup attributes for current refinements widget
         * @returns {*[]}
         */
        getCurrentRefinementsAttributes() {
            const attributes = [];
            algoliaConfig.facets.forEach(
                facet => {
                    let name = facet.attribute;

                    if (name === 'categories') {
                        name = 'categories.level0';
                    }

                    if (name === 'price') {
                        name = facet.attribute + algoliaConfig.priceKey;
                    }

                    attributes.push({
                        name : name,
                        label: facet.label ? facet.label : facet.attribute,
                    });
                }
            );
            return attributes;
        },

        startInstantSearch(search, mockAlgoliaBundle) {
            if (this.isStarted) {
                return;
            }
            search = algoliaCommon.triggerHooks(
                'beforeInstantsearchStart',
                search,
                mockAlgoliaBundle
            );
            search.start();
            search = algoliaCommon.triggerHooks(
                'afterInstantsearchStart',
                search,
                mockAlgoliaBundle
            );
            this.isStarted = true;
        },

        /**
         * Function must return an array [<widget name>: string, <widget options>: object]
         * (Same objects in array returned by implementations of getCustomAttributeFacets())
         *
         * @param facet
         * @param templates
         * @returns {[string,Object]}
         */
        getFacetWidget(facet, templates) {
            const panelOptions = this.getFacetPanelOptions(facet);

            switch (facet.type) {
                case 'priceRanges':
                    return this.getRangeInputFacetConfig(facet, templates, panelOptions);
                case 'conjunctive':
                    return this.getConjunctiveFacetConfig(facet, templates, panelOptions);
                case 'disjunctive':
                    return this.getDisjunctiveFacetConfig(facet, templates, panelOptions);
                case 'slider':
                    return this.getRangeSliderFacetConfig(facet, templates, panelOptions);
            }

            throw new Error(`[Algolia] Invalid facet widget type: ${facet.type}`);
        },

        getRangeInputFacetConfig(facet, templates, panelOptions) {
            delete templates.item;

            return [
                'rangeInput',
                {
                    container   : facet.wrapper.appendChild(
                        algoliaCommon.createISWidgetContainer(facet.attribute)
                    ),
                    attribute   : facet.attribute,
                    templates   : $.extend(
                        {
                            separatorText: algoliaConfig.translations.to,
                            submitText   : algoliaConfig.translations.go,
                        },
                        templates
                    ),
                    cssClasses  : {
                        root: 'conjunctive',
                    },
                    panelOptions: panelOptions,
                },
            ];
        },

        getConjunctiveFacetConfig(facet, templates, panelOptions) {
            let refinementListOptions = {
                container   : facet.wrapper.appendChild(
                    algoliaCommon.createISWidgetContainer(facet.attribute)
                ),
                attribute   : facet.attribute,
                limit       : algoliaConfig.maxValuesPerFacet,
                operator    : 'and',
                templates   : templates,
                sortBy      : ['count:desc', 'name:asc'],
                cssClasses  : {
                    root: 'conjunctive',
                },
                panelOptions: panelOptions,
            };

            refinementListOptions = this.addSearchForFacetValues(
                facet,
                refinementListOptions
            );

            return ['refinementList', refinementListOptions];
        },

        getDisjunctiveFacetConfig(facet, templates, panelOptions) {
            let refinementListOptions = {
                container   : facet.wrapper.appendChild(
                    algoliaCommon.createISWidgetContainer(facet.attribute)
                ),
                attribute   : facet.attribute,
                limit       : algoliaConfig.maxValuesPerFacet,
                operator    : 'or',
                templates   : templates,
                sortBy      : ['count:desc', 'name:asc'],
                panelOptions: panelOptions,
                cssClasses  : {
                    root: 'disjunctive',
                },
            };

            refinementListOptions = this.addSearchForFacetValues(
                facet,
                refinementListOptions
            );

            return ['refinementList', refinementListOptions];
        },

        getRangeSliderFacetConfig(facet, templates, panelOptions) {
            delete templates.item;

            return [
                'rangeSlider',
                {
                    container   : facet.wrapper.appendChild(
                        algoliaCommon.createISWidgetContainer(facet.attribute)
                    ),
                    attribute   : facet.attribute,
                    templates   : templates,
                    pips        : false,
                    panelOptions: panelOptions,
                    tooltips    : {
                        format(value) {
                            return facet.attribute.match(/price/) === null
                                ? parseInt(value)
                                : priceUtils.formatPrice(
                                    value,
                                    algoliaConfig.priceFormat
                                );
                        },
                    },
                },
            ];
        },

        getFacetPanelOptions(facet) {
            return  {
                templates: {
                    header: `<div class="name">${facet.label || facet.attribute}</div>`,
                },
                hidden: (options) => {
                    if (!options.results) return true;

                    const facetSearch = f => f.name === facet.attribute;

                    switch (facet.type) {
                        case 'conjunctive':
                            return !options.results.facets.find(facetSearch);
                        case 'disjunctive':
                            return !options.results.disjunctiveFacets.find(facetSearch);
                        default:
                            return false;
                    }
                },
            };
        },

        addWidget(search, type, config) {
            if (type === 'custom') {
                search.addWidgets([config]);
                return;
            }
            let widget = instantsearch.widgets[type];
            if (config.panelOptions) {
                widget = instantsearch.widgets.panel(config.panelOptions)(
                    widget
                );
                delete config.panelOptions;
            }
            if (type === 'rangeSlider' && config.attribute.indexOf('price.') < 0) {
                config.panelOptions = {
                    hidden(options) {
                        return options.range.min === 0 && options.range.max === 0;
                    },
                };
                widget = instantsearch.widgets.panel(config.panelOptions)(widget);
                delete config.panelOptions;
            }

            search.addWidgets([widget(config)]);
        },

        addSearchForFacetValues(facet, options) {
            if (facet.searchable === '1') {
                options.searchable = true;
                options.searchableIsAlwaysActive = false;
                options.searchablePlaceholder =
                    algoliaConfig.translations.searchForFacetValuesPlaceholder;
                options.templates = options.templates || {};
                options.templates.searchableNoResults =
                    '<div class="sffv-no-results">' +
                    algoliaConfig.translations.noResults +
                    '</div>';
            }

            return options;
        },

        addMobileRefinementsToggle() {
            $('#refine-toggle').on('click', function () {
                $('#instant-search-facets-container').toggleClass('hidden-sm').toggleClass('hidden-xs');
                if ($(this).html().trim()[0] === '+')
                    $(this).html('- ' + algoliaConfig.translations.refine);
                else
                    $(this).html('+ ' + algoliaConfig.translations.refine);
            });
        },

        /**
         * @deprecated algoliaBundle is going away!
         * This mock only includes libraries available to this module
         * The following have been removed:
         *  - Hogan
         *  - algoliasearchHelper
         *  - autocomplete
         *  - createAlgoliaInsightsPlugin
         *  - createLocalStorageRecentSearchesPlugin
         *  - createQuerySuggestionsPlugin
         *  - getAlgoliaResults
         * However if you've used or require any of these additional libs in your customizations,
         * you can either augment this mock as you need or include the global dependency in your module
         * and make it available to your hook.
         */
        mockAlgoliaBundle() {
            return {
                $,
                algoliasearch,
                instantsearch
            }
        }
    });

});
