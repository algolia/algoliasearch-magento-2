requirejs(['algoliaBundle', 'Magento_Catalog/js/price-utils'], function (algoliaBundle, priceUtils) {
	algoliaBundle.$(function ($) {
		/** We have nothing to do here if instantsearch is not enabled **/
		if (!algoliaConfig.instant.enabled || !(algoliaConfig.isSearchPage || !algoliaConfig.autocomplete.enabled)) {
			return;
		}

		if (!algoliaConfig.autocomplete.enabled && $(algoliaConfig.autocomplete.selector).length == 0) {
			return;
		}

		if ($(algoliaConfig.instant.selector).length <= 0) {
			throw '[Algolia] Invalid instant-search selector: ' + algoliaConfig.instant.selector;
		}

		if (algoliaConfig.autocomplete.enabled && $(algoliaConfig.instant.selector).find(algoliaConfig.autocomplete.selector).length > 0) {
			throw '[Algolia] You can\'t have a search input matching "' + algoliaConfig.autocomplete.selector +
			'" inside you instant selector "' + algoliaConfig.instant.selector + '"';
		}

		var findAutocomplete = algoliaConfig.autocomplete.enabled && $(algoliaConfig.instant.selector).find('#algolia-autocomplete-container').length > 0;
		if (findAutocomplete) {
			$(algoliaConfig.instant.selector).find('#algolia-autocomplete-container').remove();
		}

		/** BC of old hooks **/
		if (typeof algoliaHookBeforeInstantsearchInit === 'function') {
			algolia.registerHook('beforeInstantsearchInit', algoliaHookBeforeInstantsearchInit);
		}

		if (typeof algoliaHookBeforeWidgetInitialization === 'function') {
			algolia.registerHook('beforeWidgetInitialization', algoliaHookBeforeWidgetInitialization);
		}

		if (typeof algoliaHookBeforeInstantsearchStart === 'function') {
			algolia.registerHook('beforeInstantsearchStart', algoliaHookBeforeInstantsearchStart);
		}

		if (typeof algoliaHookAfterInstantsearchStart === 'function') {
			algolia.registerHook('afterInstantsearchStart', algoliaHookAfterInstantsearchStart);
		}

		/**
		 * Setup wrapper
		 *
		 * For templating is used Hogan library
		 * Docs: http://twitter.github.io/hogan.js/
		 **/
		var wrapperTemplate = algoliaBundle.Hogan.compile($('#instant_wrapper_template').html());
		var instant_selector = !algoliaConfig.autocomplete.enabled ? algoliaConfig.autocomplete.selector : "#instant-search-bar";

		var div = document.createElement('div');
		$(div).addClass('algolia-instant-results-wrapper');

		$(algoliaConfig.instant.selector).addClass('algolia-instant-replaced-content');
		$(algoliaConfig.instant.selector).wrap(div);

		$('.algolia-instant-results-wrapper').append('<div class="algolia-instant-selector-results"></div>');
		$('.algolia-instant-selector-results').html(wrapperTemplate.render({
			second_bar: algoliaConfig.autocomplete.enabled,
			findAutocomplete: findAutocomplete,
			config: algoliaConfig.instant,
			translations: algoliaConfig.translations
		})).show();

		/**
		 * Initialise instant search
		 * For rendering instant search page is used Algolia's instantsearch.js library
		 * Docs: https://www.algolia.com/doc/api-reference/widgets/instantsearch/js/
		 **/

		var ruleContexts = ['magento_filters', '']; // Empty context to keep BC for already create rules in dashboard
		if (algoliaConfig.request.categoryId.length > 0) {
			ruleContexts.push('magento-category-' + algoliaConfig.request.categoryId);
		}

		if (algoliaConfig.request.landingPageId.length > 0) {
			ruleContexts.push('magento-landingpage-' + algoliaConfig.request.landingPageId);
		}

		var searchClient = algoliaBundle.algoliasearch(algoliaConfig.applicationId, algoliaConfig.apiKey);
		var indexName = algoliaConfig.indexName + '_products';
		var searchParameters = {
			hitsPerPage: algoliaConfig.hitsPerPage,
			ruleContexts: ruleContexts
		};
		var instantsearchOptions = {
			searchClient: searchClient,
			indexName: indexName,
			searchFunction: function (helper) {
				if (helper.state.query === '' && !algoliaConfig.isSearchPage) {
					$('.algolia-instant-replaced-content').show();
					$('.algolia-instant-selector-results').hide();
				} else {
					helper.search();
					$('.algolia-instant-replaced-content').hide();
					$('.algolia-instant-selector-results').show();
				}
			},
			routing: window.routing,
		};

		instantsearchOptions = algolia.triggerHooks('beforeInstantsearchInit', instantsearchOptions, algoliaBundle);

		var search = algoliaBundle.instantsearch(instantsearchOptions);

		search.client.addAlgoliaAgent('Magento2 integration (' + algoliaConfig.extensionVersion + ')');

		/** Prepare sorting indices data */
		algoliaConfig.sortingIndices.unshift({
			name: indexName,
			label: algoliaConfig.translations.relevance
		});

		/** Setup attributes for current refinements widget **/
		var attributes = [];
		$.each(algoliaConfig.facets, function (i, facet) {
			var name = facet.attribute;

			if (name === 'categories') {
				name = 'categories.level0';
			}

			if (name === 'price') {
				name = facet.attribute + algoliaConfig.priceKey
			}

			attributes.push({
				name: name,
				label: facet.label ? facet.label : facet.attribute
			});
		});

		var allWidgetConfiguration = {
			infiniteHits: {},
			hits: {},
			configure: searchParameters,
			custom: [
				/**
				 * Custom widget - this widget is used to refine results for search page or catalog page
				 * Docs: https://www.algolia.com/doc/guides/building-search-ui/widgets/create-your-own-widgets/js/
				 **/
				{
					getWidgetSearchParameters: function(searchParameters) {
						if (algoliaConfig.request.query.length > 0 && location.hash.length < 1) {
							return searchParameters.setQuery(algoliaConfig.request.query)
						}
						return searchParameters;
					},
					init: function (data) {
						var page = data.helper.state.page;

						if (algoliaConfig.request.refinementKey.length > 0) {
							data.helper.toggleRefine(algoliaConfig.request.refinementKey, algoliaConfig.request.refinementValue);
						}

						if (algoliaConfig.isCategoryPage) {
							data.helper.addNumericRefinement('visibility_catalog', '=', 1);
						} else {
							data.helper.addNumericRefinement('visibility_search', '=', 1);
						}

						data.helper.setPage(page);
					},
					render: function (data) {
						if (!algoliaConfig.isSearchPage) {
							if (data.results.query.length === 0 && data.results.nbHits === 0) {
								$('.algolia-instant-replaced-content').show();
								$('.algolia-instant-selector-results').hide();
							} else {
								$('.algolia-instant-replaced-content').hide();
								$('.algolia-instant-selector-results').show();
							}
						}
					}
				},
				/**
				 * Custom widget - Suggestions
				 * This widget renders suggestion queries which might be interesting for your customer
				 * Docs: https://www.algolia.com/doc/guides/building-search-ui/widgets/create-your-own-widgets/js/
				 **/
				{
					suggestions: [],
					init: function () {
						if (algoliaConfig.showSuggestionsOnNoResultsPage) {
							var $this = this;
							$.each(algoliaConfig.popularQueries.slice(0, Math.min(4, algoliaConfig.popularQueries.length)), function (i, query) {
								query = $('<div>').html(query).text(); //xss
								$this.suggestions.push('<a href="' + algoliaConfig.baseUrl + '/catalogsearch/result/?q=' + encodeURIComponent(query) + '">' + query + '</a>');
							});
						}
					},
					render: function (data) {
						if (data.results.hits.length === 0) {
							var content = '<div class="no-results">';
							content += '<div><b>' + algoliaConfig.translations.noProducts + ' "' + $("<div>").text(data.results.query).html() + '</b>"</div>';
							content += '<div class="popular-searches">';

							if (algoliaConfig.showSuggestionsOnNoResultsPage && this.suggestions.length > 0) {
								content += '<div>' + algoliaConfig.translations.popularQueries + '</div>' + this.suggestions.join(', ');
							}

							content += '</div>';
							content += algoliaConfig.translations.or + ' <a href="' + algoliaConfig.baseUrl + '/catalogsearch/result/?q=__empty__">' + algoliaConfig.translations.seeAll + '</a>'

							content += '</div>';

							$('#instant-empty-results-container').html(content);
						} else {
							$('#instant-empty-results-container').html('');
						}
					}
				}
			],
			/**
			 * searchBox
			 * Docs: https://www.algolia.com/doc/api-reference/widgets/search-box/js/
			 **/
			searchBox: {
				container: instant_selector,
				placeholder: algoliaConfig.translations.searchFor,
				showSubmit: false
			},
			/**
			 * stats
			 * Docs: https://www.algolia.com/doc/api-reference/widgets/stats/js/
			 **/
			stats: {
				container: '#algolia-stats',
				templates: {
					text: function (data) {
						var hoganTemplate = algoliaBundle.Hogan.compile($('#instant-stats-template').html());

						data.first = data.page * data.hitsPerPage + 1;
						data.last = Math.min(data.page * data.hitsPerPage + data.hitsPerPage, data.nbHits);
						data.seconds = data.processingTimeMS / 1000;
						data.translations = window.algoliaConfig.translations;

						return hoganTemplate.render(data)
					}
				}
			},
			/**
			 * sortBy
			 * Docs: https://www.algolia.com/doc/api-reference/widgets/sort-by/js/
			 **/
			sortBy: {
				container: '#algolia-sorts',
				items: algoliaConfig.sortingIndices.map(function (sortingIndice) {
					return {
						label: sortingIndice.label,
						value: sortingIndice.name,
					}
				})
			},
			/**
			 * currentRefinements
			 * Widget displays all filters and refinements applied on query. It also let your customer to clear them one by one
			 * Docs: https://www.algolia.com/doc/api-reference/widgets/current-refinements/js/
			 **/
			currentRefinements: {
				container: '#current-refinements',
				templates: {
					item: $('#current-refinements-template').html()
				},
				includedAttributes: attributes.map(function (attribute) {
					return attribute.name
				}),
				transformItems: function (items) {
					return items.map(function (item) {
						var attribute = attributes.filter(function (_attribute) {
							return item.attribute === _attribute.name
						})[0];
						if (!attribute) return item;
						item.label = attribute.label;
						item.refinements.forEach(function (refinement) {
							if (refinement.type !== 'hierarchical') return refinement;
							var levels = refinement.label.split('///');
							var lastLevel = levels[levels.length - 1];
							refinement.label = lastLevel;
						});
						return item;
					})
				}
			},

			/*
			 * clearRefinements
			 * Widget displays a button that lets the user clean every refinement applied to the search. You can control which attributes are impacted by the button with the options.
			 * Docs: https://www.algolia.com/doc/api-reference/widgets/clear-refinements/js/
			 **/
			clearRefinements: {
				container: '#clear-refinements',
				templates: {
					resetLabel: algoliaConfig.translations.clearAll,
				},
				includedAttributes: attributes.map(function (attribute) {
					return attribute.name
				}),
				cssClasses: {
					button: ['action', 'primary']
				},
				transformItems: function (items) {
					return items.map(function (item) {
						var attribute = attributes.filter(function (_attribute) {
							return item.attribute === _attribute.name
						})[0];
						if (!attribute) return item;
						item.label = attribute.label;
						return item;
					})
				}
			},
			/*
			 * queryRuleCustomData
			 * The queryRuleCustomData widget displays custom data from Query Rules.
			 * Docs: https://www.algolia.com/doc/api-reference/widgets/query-rule-custom-data/js/
			 **/
			queryRuleCustomData: {
				container: '#algolia-banner',
				templates: {
					default: '{{#items}} {{#banner}} {{{banner}}} {{/banner}} {{/items}}',
				}
			}
		};

		if (algoliaConfig.instant.infiniteScrollEnabled === true) {
			/**
			 * infiniteHits
			 * This widget renders all products into result page
			 * Docs: https://www.algolia.com/doc/api-reference/widgets/infinite-hits/js/
			 **/
			allWidgetConfiguration.infiniteHits = {
				container: '#instant-search-results-container',
				templates: {
					empty: '',
					item: $('#instant-hit-template').html(),
				},
				cssClasses: {
					loadPrevious: ['action', 'primary'],
					loadMore: ['action', 'primary']
				},
				transformItems: function (items) {
					return items.map(function (item) {
						item.__indexName = search.helper.lastResults.index;
						item = transformHit(item, algoliaConfig.priceKey, search.helper);
						// FIXME: transformHit is a global
						item.isAddToCartEnabled = algoliaConfig.instant.isAddToCartEnabled;
						return item;
					});
				},
				showMoreLabel: algoliaConfig.translations.showMore,
				showPrevious: true,
				escapeHits: true
			};

			delete allWidgetConfiguration.hits;
		} else {
			/**
			 * hits
			 * This widget renders all products into result page
			 * Docs: https://www.algolia.com/doc/api-reference/widgets/hits/js/
			 **/
			allWidgetConfiguration.hits = {
				container: '#instant-search-results-container',
				templates: {
					empty: '',
					item: $('#instant-hit-template').html(),
				},
				transformItems: function (items) {
					return items.map(function (item) {
						item.__indexName = search.helper.lastResults.index;
						item = transformHit(item, algoliaConfig.priceKey, search.helper);
						// FIXME: transformHit is a global
						item.isAddToCartEnabled = algoliaConfig.instant.isAddToCartEnabled;
						item.algoliaConfig = window.algoliaConfig;
						return item;
					})
				}
			};

			/**
			 * pagination
			 * Docs: https://www.algolia.com/doc/api-reference/widgets/pagination/js/
			 **/
			allWidgetConfiguration.pagination = {
				container: '#instant-search-pagination-container',
				showFirst: false,
				showLast: false,
				showNext: true,
				showPrevious: true,
				totalPages: 1000,
				templates: {
					previous: algoliaConfig.translations.previousPage,
					next: algoliaConfig.translations.nextPage
				},
			};

			delete allWidgetConfiguration.infiniteHits;
		}

		/**
		 * Here are specified custom attributes widgets which require special code to run properly
		 * Custom widgets can be added to this object like [attribute]: function(facet, templates)
		 * Function must return an array [<widget name>: string, <widget options>: object]
		 **/
		var customAttributeFacet = {
			categories: function (facet, templates) {
				var hierarchical_levels = [];
				for (var l = 0; l < 10; l++) {
					hierarchical_levels.push('categories.level' + l.toString());
				}

				var hierarchicalMenuParams = {
					container: facet.wrapper.appendChild(createISWidgetContainer(facet.attribute)),
					attributes: hierarchical_levels,
					separator: ' /// ',
					templates: templates,
					alwaysGetRootLevel: true,
					limit: algoliaConfig.maxValuesPerFacet,
					sortBy: ['name:asc']
				};

				hierarchicalMenuParams.templates.item = '' +
					'<a class="{{cssClasses.link}} {{#isRefined}}{{cssClasses.link}}--selected{{/isRefined}}" href="{{url}}">{{label}}' + ' ' +
					'<span class="{{cssClasses.count}}">{{#helpers.formatNumber}}{{count}}{{/helpers.formatNumber}}</span>' + ' ' +
					'{{#isRefined}}<span class="cross-circle"></span>{{/isRefined}}' +
					'</a>';
				hierarchicalMenuParams.panelOptions = {
					templates: {
						header: '<div class="name">' + (facet.label ? facet.label : facet.attribute) + '</div>',
					}
				};

				return ['hierarchicalMenu', hierarchicalMenuParams];
			}
		};

		/** Add all facet widgets to instantsearch object **/
		window.getFacetWidget = function (facet, templates) {
			var panelOptions = {
				templates: {
					header: '<div class="name">'
						+ (facet.label ? facet.label : facet.attribute)
						+ '</div>',
				},
				hidden: function (options) {
					if (!options.results) return true;
					switch (facet.type) {
						case 'conjunctive':
							var facetsNames = options.results.facets.map(function (f) {
								return f.name
							});
							return facetsNames.indexOf(facet.attribute) === -1;
						case 'disjunctive':
							var disjunctiveFacetsNames = options.results.disjunctiveFacets.map(function (f) {
								return f.name
							});
							return disjunctiveFacetsNames.indexOf(facet.attribute) === -1;
						default:
							return false;
					}
				}
			};
			if (facet.type === 'priceRanges') {
				delete templates.item;

				return ['priceRanges', {
					container: facet.wrapper.appendChild(createISWidgetContainer(facet.attribute)),
					attribute: facet.attribute,
					labels: {
						currency: algoliaConfig.currencySymbol,
						separator: algoliaConfig.translations.to,
						button: algoliaConfig.translations.go
					},
					templates: templates,
					cssClasses: {
						root: 'conjunctive'
					},
					panelOptions: panelOptions,
				}];
			}

			if (facet.type === 'conjunctive') {
				var refinementListOptions = {
					container: facet.wrapper.appendChild(createISWidgetContainer(facet.attribute)),
					attribute: facet.attribute,
					limit: algoliaConfig.maxValuesPerFacet,
					operator: 'and',
					templates: templates,
					sortBy: ['count:desc', 'name:asc'],
					cssClasses: {
						root: 'conjunctive'
					},
					panelOptions: panelOptions
				};

				refinementListOptions = addSearchForFacetValues(facet, refinementListOptions);

				return ['refinementList', refinementListOptions];
			}

			if (facet.type === 'disjunctive') {
				var refinementListOptions = {
					container: facet.wrapper.appendChild(createISWidgetContainer(facet.attribute)),
					attribute: facet.attribute,
					limit: algoliaConfig.maxValuesPerFacet,
					operator: 'or',
					templates: templates,
					sortBy: ['count:desc', 'name:asc'],
					panelOptions: panelOptions,
					cssClasses: {
						root: 'disjunctive'
					}
				};

				refinementListOptions = addSearchForFacetValues(facet, refinementListOptions);

				return ['refinementList', refinementListOptions];
			}

			if (facet.type === 'slider') {
				delete templates.item;

				return ['rangeSlider', {
					container: facet.wrapper.appendChild(createISWidgetContainer(facet.attribute)),
					attribute: facet.attribute,
					templates: templates,
					pips: false,
					panelOptions: panelOptions,
					tooltips: {
						format: function (formattedValue) {
							return facet.attribute.match(/price/) === null ?
								parseInt(formattedValue) :
								priceUtils.formatPrice(formattedValue, algoliaConfig.priceFormat);
						}
					}
				}];
			}
		};

		var wrapper = document.getElementById('instant-search-facets-container');
		$.each(algoliaConfig.facets, function (i, facet) {

			if (facet.attribute.indexOf("price") !== -1)
				facet.attribute = facet.attribute + algoliaConfig.priceKey;

			facet.wrapper = wrapper;

			var templates = {
				item: $('#refinements-lists-item-template').html()
			};

			var widgetInfo = customAttributeFacet[facet.attribute] !== undefined ?
				customAttributeFacet[facet.attribute](facet, templates) :
				getFacetWidget(facet, templates);

			var widgetType = widgetInfo[0],
				widgetConfig = widgetInfo[1];

			if (typeof allWidgetConfiguration[widgetType] === 'undefined') {
				allWidgetConfiguration[widgetType] = [widgetConfig];
			} else {
				allWidgetConfiguration[widgetType].push(widgetConfig);
			}
		});

		if (algoliaConfig.analytics.enabled) {
			if (typeof algoliaAnalyticsPushFunction !== 'function') {
				var algoliaAnalyticsPushFunction = function (formattedParameters, state, results) {
					var trackedUrl = '/catalogsearch/result/?q=' + state.query + '&' + formattedParameters + '&numberOfHits=' + results.nbHits;

					// Universal Analytics
					if (typeof window.ga !== 'undefined') {
						window.ga('set', 'page', trackedUrl);
						window.ga('send', 'pageView');
					}
				};
			}

			allWidgetConfiguration['analytics'] = {
				pushFunction: algoliaAnalyticsPushFunction,
				delay: algoliaConfig.analytics.delay,
				triggerOnUIInteraction: algoliaConfig.analytics.triggerOnUiInteraction,
				pushInitialSearch: algoliaConfig.analytics.pushInitialSearch
			};
		}

		allWidgetConfiguration = algolia.triggerHooks('beforeWidgetInitialization', allWidgetConfiguration, algoliaBundle);

		$.each(allWidgetConfiguration, function (widgetType, widgetConfig) {
			if (Array.isArray(widgetConfig) === true) {
				$.each(widgetConfig, function (i, widgetConfig) {
					addWidget(search, widgetType, widgetConfig);
				});
			} else {
				addWidget(search, widgetType, widgetConfig);
			}
		});

		var isStarted = false;

		function startInstantSearch() {
			if (isStarted === true) {
				return;
			}

			search = algolia.triggerHooks('beforeInstantsearchStart', search, algoliaBundle);
			search.start();
			search = algolia.triggerHooks('afterInstantsearchStart', search, algoliaBundle);

			isStarted = true;
		}

		/** Initialise searching **/
		startInstantSearch();
	});

	function addWidget(search, type, config) {
		if (type === 'custom') {
			search.addWidgets([config]);
			return;
		}
		var widget = algoliaBundle.instantsearch.widgets[type];
		if (config.panelOptions) {
			widget = algoliaBundle.instantsearch.widgets.panel(config.panelOptions)(widget);
			delete config.panelOptions;
		}

		search.addWidgets([widget(config)]);
	}

	function addSearchForFacetValues(facet, options) {
		if (facet.searchable === '1') {
			options.searchable = true;
			options.searchableIsAlwaysActive = false;
			options.searchablePlaceholder = algoliaConfig.translations.searchForFacetValuesPlaceholder;
			options.templates = options.templates || {};
			options.templates.searchableNoResults = '<div class="sffv-no-results">' + algoliaConfig.translations.noResults + '</div>';
		}

		return options;
	}
});
