requirejs(['algoliaBundle'], function(algoliaBundle) {
	algoliaBundle.$(function ($) {
		
		/** We have nothing to do here if instantsearch is not enabled **/
		if (!algoliaConfig.instant.enabled || !(algoliaConfig.isSearchPage || !algoliaConfig.autocomplete.enabled)) {
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
		 * Docs: https://community.algolia.com/instantsearch.js/documentation/
		 **/
		var search = algoliaBundle.instantsearch({
			appId: algoliaConfig.applicationId,
			apiKey: algoliaConfig.apiKey,
			indexName: algoliaConfig.indexName + '_products',
			urlSync: {
				useHash: true,
				trackedParameters: ['query', 'page', 'attribute:*', 'index']
			}
		});
		
		search.client.addAlgoliaAgent('Magento integration (' + algoliaConfig.extensionVersion + ')');
		
		/**
		 * Custom widget - this widget is used to refine results for search page or catalog page
		 * Docs: https://community.algolia.com/instantsearch.js/documentation/#custom-widgets
		 **/
		search.addWidget({
			getConfiguration: function () {
				if (algoliaConfig.request.query.length > 0 && location.hash.length < 1) {
					return {query: algoliaConfig.request.query}
				}
				return {};
			},
			init: function (data) {
				if (algoliaConfig.request.refinementKey.length > 0) {
					data.helper.toggleRefine(algoliaConfig.request.refinementKey, algoliaConfig.request.refinementValue);
				}
				
				if (algoliaConfig.isCategoryPage) {
					data.helper.addNumericRefinement('visibility_catalog', '=', 1);
				}
				else {
					data.helper.addNumericRefinement('visibility_search', '=', 1);
				}
			},
			render: function (data) {
				if (!algoliaConfig.isSearchPage) {
					if (data.results.query.length === 0) {
						$('.algolia-instant-replaced-content').show();
						$('.algolia-instant-selector-results').hide();
					}
					else {
						$('.algolia-instant-replaced-content').hide();
						$('.algolia-instant-selector-results').show();
					}
				}
			}
		});
		
		/**
		 * Search box
		 * Docs: https://community.algolia.com/instantsearch.js/documentation/#searchbox
		 **/
		search.addWidget(
			algoliaBundle.instantsearch.widgets.searchBox({
				container: instant_selector,
				placeholder: algoliaConfig.translations.searchFor
			})
		);
		
		/**
		 * Stats
		 * Docs: https://community.algolia.com/instantsearch.js/documentation/#stats
		 **/
		search.addWidget(
			algoliaBundle.instantsearch.widgets.stats({
				container: '#algolia-stats',
				templates: {
					body: $('#instant-stats-template').html()
				},
				transformData: function (data) {
					data.first = data.page * data.hitsPerPage + 1;
					data.last = Math.min(data.page * data.hitsPerPage + data.hitsPerPage, data.nbHits);
					data.seconds = data.processingTimeMS / 1000;
					
					data.translations = window.algoliaConfig.translations;
					
					return data;
				}
			})
		);
		
		/**
		 * Sorting
		 * Docs: https://community.algolia.com/instantsearch.js/documentation/#sortbyselector
		 **/
		algoliaConfig.sortingIndices.unshift({
			name: algoliaConfig.indexName + '_products',
			label: algoliaConfig.translations.relevance
		});
		
		search.addWidget(
			algoliaBundle.instantsearch.widgets.sortBySelector({
				container: '#algolia-sorts',
				indices: algoliaConfig.sortingIndices,
				cssClass: 'form-control'
			})
		);
		
		/**
		 * Products' hits
		 * This widget renders all products into result page
		 * Docs: https://community.algolia.com/instantsearch.js/documentation/#hits
		 **/
		search.addWidget(
			algoliaBundle.instantsearch.widgets.hits({
				container: '#instant-search-results-container',
				templates: {
					item: $('#instant-hit-template').html()
				},
				transformData: {
					item: function (hit) {
						hit = transformHit(hit, algoliaConfig.priceKey);
						hit.isAddToCartEnabled = algoliaConfig.instant.isAddToCartEnabled;
						
						hit.algoliaConfig = window.algoliaConfig;
						
						return hit;
					}
				},
				hitsPerPage: algoliaConfig.hitsPerPage
			})
		);
		
		/**
		 * Custom widget - Suggestions
		 * This widget renders suggestion queries which might be interesting for your customer
		 * Docs: https://community.algolia.com/instantsearch.js/documentation/#custom-widgets
		 **/
		search.addWidget({
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
					
					$('#instant-search-results-container').html(content);
				}
			}
		});
		
		/** Setup attributes for current refinements widget **/
		var attributes = [];
		$.each(algoliaConfig.facets, function (i, facet) {
			var name = facet.attribute;
			
			if (name === 'categories') {
				if (algoliaConfig.isCategoryPage) {
					return;
				}
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
		
		/**
		 * Widget name: Current refinements
		 * Widget displays all filters and refinements applied on query. It also let your customer to clear them one by one
		 * Docs: https://community.algolia.com/instantsearch.js/documentation/#currentrefinedvalues
		 **/
		search.addWidget(
			algoliaBundle.instantsearch.widgets.currentRefinedValues({
				container: '#current-refinements',
				cssClasses: {
					root: 'facet'
				},
				templates: {
					header: '<div class="name">' + algoliaConfig.translations.selectedFilters + '</div>',
					clearAll: algoliaConfig.translations.clearAll,
					item: $('#current-refinements-template').html()
				},
				attributes: attributes,
				onlyListedAttributes: true
			})
		);
		
		/**
		 * Here are specified custom attributes widgets which require special code to run properly
		 * Custom widgets can be added to this object like [attributeName]: function(facet, templates)
		 * Function must return instantsearch.widget object
		 * Docs: https://community.algolia.com/instantsearch.js/documentation/#widgets
		 **/
		var customAttributeFacet = {
			categories: function (facet, templates) {
				var hierarchical_levels = [];
				for (var l = 0; l < 10; l++)
					hierarchical_levels.push('categories.level' + l.toString());
				
				var hierarchicalMenuParams = {
					container: facet.wrapper.appendChild(document.createElement('div')),
					attributes: hierarchical_levels,
					separator: ' /// ',
					alwaysGetRootLevel: true,
					limit: algoliaConfig.maxValuesPerFacet,
					templates: templates,
					sortBy: ['name:asc'],
					cssClasses: {
						list: 'hierarchical',
						root: 'facet hierarchical'
					}
				};
				
				hierarchicalMenuParams.templates.item = '' +
					'<div class="ais-hierearchical-link-wrapper">' +
					'<a class="{{cssClasses.link}}" href="{{url}}">{{name}}' +
					'{{#isRefined}}<span class="cross-circle"></span>{{/isRefined}}' +
					'<span class="{{cssClasses.count}}">{{#helpers.formatNumber}}{{count}}{{/helpers.formatNumber}}</span></a>' +
					'</div>';
				
				if (algoliaConfig.request.path.length > 0) {
					hierarchicalMenuParams.rootPath = algoliaConfig.request.path;
				}
				
				return algoliaBundle.instantsearch.widgets.hierarchicalMenu(hierarchicalMenuParams);
			}
		};
		
		/** Add all facet widgets to instatnsearch object **/
		
		window.getFacetWidget = function (facet, templates) {
			
			if (facet.type === 'priceRanges') {
				delete templates.item;
				
				return algoliaBundle.instantsearch.widgets.priceRanges({
					container: facet.wrapper.appendChild(document.createElement('div')),
					attributeName: facet.attribute,
					labels: {
						currency: algoliaConfig.currencySymbol,
						separator: algoliaConfig.translations.to,
						button: algoliaConfig.translations.go
					},
					templates: templates,
					cssClasses: {
						root: 'facet conjunctive'
					}
				})
			}
			
			if (facet.type === 'conjunctive') {
				return algoliaBundle.instantsearch.widgets.refinementList({
					container: facet.wrapper.appendChild(document.createElement('div')),
					attributeName: facet.attribute,
					limit: algoliaConfig.maxValuesPerFacet,
					operator: 'and',
					templates: templates,
					cssClasses: {
						root: 'facet conjunctive'
					}
				});
			}
			
			if (facet.type === 'disjunctive') {
				return algoliaBundle.instantsearch.widgets.refinementList({
					container: facet.wrapper.appendChild(document.createElement('div')),
					attributeName: facet.attribute,
					limit: algoliaConfig.maxValuesPerFacet,
					operator: 'or',
					templates: templates,
					cssClasses: {
						root: 'facet disjunctive'
					}
				});
			}
			
			if (facet.type == 'slider') {
				delete templates.item;
				
				return algoliaBundle.instantsearch.widgets.rangeSlider({
					container: facet.wrapper.appendChild(document.createElement('div')),
					attributeName: facet.attribute,
					templates: templates,
					cssClasses: {
						root: 'facet slider'
					},
					tooltips: {
						format: function (formattedValue) {
							return parseInt(formattedValue);
						}
					}
				});
			}
		};
		
		var wrapper = document.getElementById('instant-search-facets-container');
		$.each(algoliaConfig.facets, function (i, facet) {
			
			if (facet.attribute.indexOf("price") !== -1)
				facet.attribute = facet.attribute + algoliaConfig.priceKey;
			
			facet.wrapper = wrapper;
			
			var templates = {
				header: '<div class="name">' + (facet.label ? facet.label : facet.attribute) + '</div>',
				item: $('#refinements-lists-item-template').html()
			};
			
			var widget = customAttributeFacet[facet.attribute] !== undefined ?
				customAttributeFacet[facet.attribute](facet, templates) :
				getFacetWidget(facet, templates);
			
			search.addWidget(widget);
		});
		
		/**
		 * Pagination
		 * Docs: https://community.algolia.com/instantsearch.js/documentation/#pagination
		 **/
		search.addWidget(
			algoliaBundle.instantsearch.widgets.pagination({
				container: '#instant-search-pagination-container',
				cssClass: 'algolia-pagination',
				showFirstLast: false,
				maxPages: 1000,
				labels: {
					previous: algoliaConfig.translations.previousPage,
					next: algoliaConfig.translations.nextPage
				},
				scrollTo: 'body'
			})
		);
		
		var isStarted = false;
		function startInstantSearch() {
			if(isStarted == true) {
				return;
			}
			
			search.start();
			
			handleInputCrossInstant($(instant_selector));
			
			var instant_search_bar = $(instant_selector);
			if (instant_search_bar.is(":focus") === false) {
				focusInstantSearchBar(search, instant_search_bar);
			}
			
			if (algoliaConfig.autocomplete.enabled) {
				$('#search_mini_form').addClass('search-page');
			}
			
			$(document).on('click', '.ais-hierarchical-menu--link, .ais-refinement-list--checkbox', function () {
				focusInstantSearchBar(search, instant_search_bar);
			});
			
			isStarted = true;
		}
		
		/** Initialise searching **/
		startInstantSearch();
	});
});