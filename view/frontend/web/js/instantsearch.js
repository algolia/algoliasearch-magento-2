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

        ///////////////////////////
        //       Properties      //
        ///////////////////////////

        isStarted: false,

        minQuerySuggestions: 4,

        dynamicWidgets: [],

        ///////////////////////////
        //  Main build functions //
        ///////////////////////////

        initialize(_config, _element) {
            this.buildInstantSearch().then(() => console.log("[Algolia] InstantSearch build complete"));
        },

        /**
         * Load and display search results using Algolia's InstantSearch.js library v4
         *
         * This is the main entry point for building the Magento InstantSearch experience.
         *
         * Rough overview of build process:
         *
         * - Initializes dependencies
         * - Creates the DOM elements where InstantSearch widgets will be inserted on the PLP (aka the "wrapper")
         * - Creates the InstantSearch object with configured options
         * - All widgets are preconfigured using the `allWidgetConfiguration` object
         *      - This object houses all widgets to be displayed in the frontend experience and is important for customization
         *      - Passed to `beforeWidgetInitialization` hook
         *      - Implementation is specific to Magento index object data structure
         * - Loads `allWidgetConfiguration` into InstantSearch
         * - Starts InstantSearch which adds the widgets to the DOM and performs first search
         *
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

            if (algoliaConfig.instant.isAddToCartEnabled) {
                this.handleAddToCart(search);
            }

            this.startInstantSearch(search, mockAlgoliaBundle);

            this.addMobileRefinementsToggle();
        },

        /**
         * Build wrapper DOM object to contain InstantSearch widgets
         *
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
         * Load the supplied widget configuration into InstantSearch
         *
         * Triggers the hook: beforeWidgetInitialization
         *
         * @param search
         * @param allWidgetConfiguration
         * @param mockAlgoliaBundle
         */
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

            if (this.dynamicWidgets.length) {
                this.initializeDynamicWidgets(search);
            }
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
                configure          : this.getSearchParameters(),
                custom             : this.getCustomWidgets(),
                stats              : this.getStatsWidget(templateProcessor),
                sortBy             : this.getSortByWidget(),
                queryRuleCustomData: this.getQueryRuleCustomDataWidget(),
            };

            if (algoliaConfig.instant.isSearchBoxEnabled) {
                allWidgetConfiguration.searchBox = this.getSearchBoxWidget()
            }

            allWidgetConfiguration = this.configureHits(allWidgetConfiguration, search);

            allWidgetConfiguration = this.configureRefinements(allWidgetConfiguration);

            allWidgetConfiguration = this.configureFacets(allWidgetConfiguration);

            if (algoliaConfig.analytics.enabled) {
                allWidgetConfiguration.analytics = this.getAnalyticsWidget()
            }

            return allWidgetConfiguration;
        },

        /**
         * Process a passed widget config object and add to InstantSearch
         * Dynamic widgets are deferred as they must be aggregated and processed separately
         *
         * @param search InstantSearch object
         * @param type True InstantSearch widget type
         * @param config Widget config object
         */
        addWidget(search, type, config) {
            if (type === 'custom') {
                search.addWidgets([config]);
                return;
            }

            if (algoliaConfig.instant.isDynamicFacetsEnabled && this.isDynamicFacetsEligible(type)) {
                // we cannot pre-bake the dynamicWidget - defer and package the type with the config
                this.dynamicWidgets.push({ ...config, type });
                return;
            }

            search.addWidgets([this.getConfiguredWidget(instantsearch.widgets[type], config)]);
        },

        /**
         * Return a fully configured widget, panelized (as needed) based on the supplied raw config object
         * @param widget
         * @param config
         */
        getConfiguredWidget(widget, config) {
            if (config.panelOptions) {
                widget = instantsearch.widgets.panel(config.panelOptions)(widget);
                delete config.panelOptions; // facet config attribute only NOT IS widget attribute
            }
            return widget(config);
        },

        /**
         * Assigns designated facets to InstantSearch dynamicWidgets
         *
         * Docs: https://www.algolia.com/doc/api-reference/widgets/dynamic-facets/js/
         * @param search
         */
        initializeDynamicWidgets(search) {
            const { dynamicWidgets } = instantsearch.widgets;
            search.addWidget(
                dynamicWidgets({
                    container: '#instant-search-facets-container',
                    widgets: this.dynamicWidgets.map(config => {
                        const { type, ...raw } = config;
                        const widget = instantsearch.widgets[type];
                        // The dynamicWidgets container must be derived at run time
                        return container => {
                            return this.getConfiguredWidget(
                                widget,
                                {
                                    ...raw,
                                    container
                                }
                            );
                        };
                    })
                })
            );
        },

        /**
         * Determines which widgets will be included for dynamic faceting
         * Does not rely on algoliaConfig.facets in case custom facets have been defined
         *
         * @param widgetType
         * @returns {boolean}
         */
        isDynamicFacetsEligible(widgetType) {
            return [
                'refinementList',
                'menu',
                'hierarchicalMenu',
                'numericMenu',
                'rangeInput',
                'rangeSlider',
                'ratingMenu',
                'toggleRefinement'
            ].includes(widgetType);
        },

        /**
         * Starts InstantSearch which adds all pre-loaded widgets to the DOM and triggers the first search
         *
         * Docs: https://www.algolia.com/doc/api-reference/widgets/instantsearch/js/#widget-param-start
         *
         * Triggers the hooks:
         *  - beforeInstantsearchStart
         *  - afterInstantsearchStart
         *
         * @param search
         * @param mockAlgoliaBundle
         */
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

        ////////////////////////////
        //     Search results     //
        ////////////////////////////

        /**
         * Setup hits and pagination based on configuration
         *
         * @param allWidgetConfiguration
         * @param search
         * @returns {*}
         */
        configureHits(allWidgetConfiguration, search) {
            if (algoliaConfig.instant.infiniteScrollEnabled) {
                allWidgetConfiguration.infiniteHits = this.getInfiniteHitsWidget(search);
            } else {
                allWidgetConfiguration.hits = this.getHitsWidget(search);
                allWidgetConfiguration.pagination = this.getPaginationWidget();
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
        getHitsWidget(search) {
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
        getPaginationWidget() {
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
        getInfiniteHitsWidget(search) {
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
        getSearchBoxWidget() {
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

        /**
         * stats
         * Docs: https://www.algolia.com/doc/api-reference/widgets/stats/js/
         *
         * @param templateProcessor
         * @returns {{container: string, templates: {text: (function(*): *)}}}
         */
        getStatsWidget(templateProcessor) {
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
        getSortByWidget() {
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

        ////////////////////////////
        //        FACETS          //
        ////////////////////////////

        /**
         * Add all facet widgets to allWidgetConfiguration
         * This is dynamically driven by the Magento facet configuration
         * Invokes facet builder function by attribute or type (where attribute builders take precedence)
         * The builders are responsible for flushing out the widget configuration for each facet
         *
         * @param allWidgetConfiguration
         * @returns {*}
         */
        configureFacets(allWidgetConfiguration) {
            const customFacetBuilders = this.getCustomAttributeFacetBuilders();

            const wrapper = document.getElementById('instant-search-facets-container');
            algoliaConfig.facets.forEach(
                facet => {
                    facet.wrapper = wrapper;

                    if (facet.attribute.includes('price')) {
                        facet.attribute += algoliaConfig.priceKey;
                    }

                    const facetBuilder = customFacetBuilders[facet.attribute] ?? this.getFacetConfig.bind(this);

                    const widgetInfo = facetBuilder(facet);

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

        ////////////////////////////
        //     Facets by TYPE     //
        ////////////////////////////

        /**
         * This is a generic facet builder that builds a widget config by facet *TYPE*
         * (Defined facet types are Magento specific and not valid InstantSearch widget types)
         *
         * Function must return an array [<widget name>: string, <widget options>: object]
         * (Same objects in array returned by implementations of `getCustomAttributeFacetBuilders()`)
         *
         * @param facet
         * @returns {[string,Object]} The second element in the array is a config for a *facet*
         *     The config contains regular IS widget specific details + `panelOptions`
         *     Although extra object properties are silently ignored it is important to distinguish these
         *     objects as they must be processed further before passing directly to InstantSearch
         *
         * @see getCustomAttributeFacetBuilders
         */
        getFacetConfig(facet) {
            switch (facet.type) {
                case 'priceRanges':
                    return this.getRangeInputFacetConfig(facet);
                case 'conjunctive':
                    return this.getConjunctiveFacetConfig(facet);
                case 'disjunctive':
                    return this.getDisjunctiveFacetConfig(facet);
                case 'slider':
                    return this.getRangeSliderFacetConfig(facet);
            }

            throw new Error(`[Algolia] Invalid facet widget type: ${facet.type}`);
        },

        /**
         * Docs: https://www.algolia.com/doc/api-reference/widgets/range-input/js/
         */
        getRangeInputFacetConfig(facet) {
            return [
                'rangeInput',
                {
                    container   : facet.wrapper.appendChild(
                        algoliaCommon.createISWidgetContainer(facet.attribute)
                    ),
                    attribute   : facet.attribute,
                    templates   : {
                        separatorText: algoliaConfig.translations.to,
                        submitText   : algoliaConfig.translations.go,
                    },
                    cssClasses  : {
                        root: 'conjunctive',
                    },
                    panelOptions: this.getPricingFacetPanelOptions(facet)
                },
            ];
        },

        /**
         * Docs: https://www.algolia.com/doc/api-reference/widgets/range-slider/js/
         */
        getRangeSliderFacetConfig(facet) {
            return [
                'rangeSlider',
                {
                    container   : facet.wrapper.appendChild(
                        algoliaCommon.createISWidgetContainer(facet.attribute)
                    ),
                    attribute   : facet.attribute,
                    pips        : false,
                    panelOptions: this.getPricingFacetPanelOptions(facet),
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

        getPricingFacetPanelOptions(facet) {
            return {
                templates: this.getDefaultFacetPanelTemplates(facet),
                hidden(options) {
                    return options.range.min === options.range.max;
                }
            }
        },

        /**
         * Docs: https://www.algolia.com/doc/api-reference/widgets/refinement-list/js/
         */
        getConjunctiveFacetConfig(facet) {
            const defaultOptions = this.getRefinementListOptions(facet);

            const refinementListOptions = {
                ...defaultOptions,
                operator    : 'and',
                cssClasses  : {
                    root: 'conjunctive',
                }
            };

            return ['refinementList', this.addSearchForFacetValues(facet, refinementListOptions)];
        },

        /**
         * Docs: https://www.algolia.com/doc/api-reference/widgets/refinement-list/js/
         */
        getDisjunctiveFacetConfig(facet) {
            const defaultOptions = this.getRefinementListOptions(facet);

            const refinementListOptions = {
                ...defaultOptions,
                operator    : 'or',
                cssClasses  : {
                    root: 'disjunctive',
                }
            }

            return ['refinementList', this.addSearchForFacetValues(facet, refinementListOptions)];
        },

        getRefinementListOptions(facet) {
            return {
                container   : facet.wrapper.appendChild(
                    algoliaCommon.createISWidgetContainer(facet.attribute)
                ),
                attribute   : facet.attribute,
                limit       : algoliaConfig.maxValuesPerFacet,
                templates   : this.getRefinementsListTemplates(),
                sortBy      : ['count:desc', 'name:asc'],
                panelOptions: this.getRefinementFacetPanelOptions(facet)
            };
        },

        getRefinementFacetPanelOptions(facet) {
            return  {
                templates: this.getDefaultFacetPanelTemplates(facet),
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

        getDefaultFacetPanelTemplates(facet) {
            return {
                header: `<div class="name">${facet.label || facet.attribute}</div>`,
            };
        },

        addSearchForFacetValues(facet, options) {
            if (facet.searchable === '1') {
                options.searchable = true;
                options.searchableIsAlwaysActive = false;
                options.searchablePlaceholder =
                    algoliaConfig.translations.searchForFacetValuesPlaceholder;
                options.templates = options.templates || {};
                options.templates.searchableNoResults =
                    `<div class="sffv-no-results">${algoliaConfig.translations.noResults}</div>`;
            }

            return options;
        },

        /**
         * @returns {{item: string}}
         */
        getRefinementsListTemplates() {
            return {
                item: this.getTemplateContentsFromDOM('#refinements-lists-item-template')
            };
        },

        ////////////////////////////
        //  Facets by ATTRIBUTE   //
        ////////////////////////////

        /**
         * Here are specified custom attributes widgets which require special code to run properly
         * The facet builder returns by *ATTRIBUTE*
         * Generic facets by *type* are built by getFacetConfig()
         *
         * Custom widgets can be added to this object like [attribute]: function(facet)
         * Function must return an array [<widget name>: string, <widget options>: object]
         * (Same as getFacetConfig() which handles generic facets)
         *
         * Any facet builders returned by this function will take precedence over getFacetConfig()
         *
         * Triggers the hook: beforeFacetInitialization
         *
         * @returns {Object<string, function>}
         * @see getFacetConfig
         */
        getCustomAttributeFacetBuilders() {
            const builders = {
                categories: this.getCategoriesFacetConfigBuilder()
            };

            return algoliaCommon.triggerHooks(
                'beforeFacetInitialization',
                builders
            );
        },

        /**
         * Get custom attribute function to generate config to ultimately build a categories hierarchicalMenu widget
         *
         * Docs: https://www.algolia.com/doc/api-reference/widgets/hierarchical-menu/js/
         */
        getCategoriesFacetConfigBuilder() {
            return (facet) => {
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
                    templates      : [],
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

        ////////////////////////////
        //      Refinements       //
        ////////////////////////////

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

        /**
         * Loads refinements management capabilities
         * i.e. As refinements are applied to search results via faceting,
         * this feature allows you to selectively remove one or all refinements.
         *
         * @param allWidgetConfiguration
         * @returns {*}
         */
        configureRefinements(allWidgetConfiguration) {
            const currentRefinementsAttributes = this.getCurrentRefinementsAttributes();
            allWidgetConfiguration.currentRefinements = this.getCurrentRefinementsWidget(currentRefinementsAttributes);
            allWidgetConfiguration.clearRefinements = this.getClearRefinementsWidget(currentRefinementsAttributes);
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
        getCurrentRefinementsWidget(currentRefinementsAttributes) {
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
        getClearRefinementsWidget(currentRefinementsAttributes) {
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

        ////////////////////////////
        //     Custom widgets     //
        ////////////////////////////

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

        ////////////////////////////
        //     Merchandising      //
        ////////////////////////////

        /**
         * queryRuleCustomData
         * The queryRuleCustomData widget displays custom data from Query Rules.
         * Docs: https://www.algolia.com/doc/api-reference/widgets/query-rule-custom-data/js/
         *
         * @returns {{container: string, templates: {default: string}}}
         */
        getQueryRuleCustomDataWidget() {
            return {
                container: '#algolia-banner',
                templates: {
                    default: '{{#items}} {{#banner}} {{{banner}}} {{/banner}} {{/items}}',
                },
            };
        },

        ////////////////////////////
        //      Configuration     //
        ////////////////////////////

        /**
         * Get the configuration options for creating the InstantSearch object
         * Docs: https://www.algolia.com/doc/api-reference/widgets/instantsearch/js/#options
         *
         * Triggers the hook: beforeInstantsearchInit
         *
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

        ////////////////////////////
        //    Utility functions   //
        ////////////////////////////

        getProductIndexName() {
            return algoliaConfig.indexName + '_products';
        },

        /**
         * NOTE: The initial (relevant) sort is based on the main index
         */
        prepareSortingIndices() {
            algoliaConfig.sortingIndices.unshift({
                name : this.getProductIndexName(),
                label: algoliaConfig.translations.relevance,
            });
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
         * @returns {string}
         */
        getAlgoliaAgent() {
            return 'Magento2 integration (' + algoliaConfig.extensionVersion + ')';
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
         * Capture active redirect URL with IS facet params for add to cart from PLP
         * @param search
         */
        handleAddToCart(search) {
            search.on('render', () => {
                const cartForms = document.querySelectorAll(
                    '[data-role="tocart-form"]'
                );
                cartForms.forEach((form) => {
                    form.addEventListener('submit', e => {
                        const url = `${algoliaConfig.request.url}${window.location.search}`;
                        e.target.elements[
                            algoliaConfig.instant.addToCartParams.redirectUrlParam
                            ].value = algoliaBase64.mageEncode(url);
                    });
                });
            });
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

        ///////////////////////////
        //       Deprecated      //
        ///////////////////////////

        /**
         * @deprecated Preserved for backward compat but this widget uses Universal Analytics which was sunsetted July 1, 2023
         * TODO: Introduce GA4
         */
        getAnalyticsWidget() {
            return {
                pushFunction(formattedParameters, state, results) {
                    const trackedUrl =
                        '/catalogsearch/result/?q=' +
                        state.query +
                        '&' +
                        formattedParameters +
                        '&numberOfHits=' +
                        results.nbHits;

                    if (typeof window.ga !== 'undefined') {
                        window.ga('set', 'page', trackedUrl);
                        window.ga('send', 'pageView');
                    }
                },
                delay                 : algoliaConfig.analytics.delay,
                triggerOnUIInteraction: algoliaConfig.analytics.triggerOnUiInteraction,
                pushInitialSearch     : algoliaConfig.analytics.pushInitialSearch,
            };
        },

        /**
         * @deprecated This method has been renamed - as the method does not return a true widget
         *             but rather an integration specific config structure that also contains `panelOptions`
         *
         *             The `templates` parameter is also now no longer used
         * @see getFacetConfig
         */
        getFacetWidget(facet, _templates) {
            return this.getFacetConfig(facet);
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
        },

        /**
         * @deprecated - these old hooks are scheduled to be removed in version 3.17
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
    });

});
