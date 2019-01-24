requirejs(['algoliaBundle'], function(algoliaBundle) {
	algoliaBundle.$(function ($) {
		
		/** We have nothing to do here if autocomplete is disabled **/
		if (!algoliaConfig.autocomplete.enabled) {
			return;
		}
		
		/**
		 * Set autocomplete templates
		 * For templating is used Hogan library
		 * Docs: http://twitter.github.io/hogan.js/
		 **/
		algoliaConfig.autocomplete.templates = {
			suggestions: algoliaBundle.Hogan.compile($('#autocomplete_suggestions_template').html()),
			products: algoliaBundle.Hogan.compile($('#autocomplete_products_template').html()),
			categories: algoliaBundle.Hogan.compile($('#autocomplete_categories_template').html()),
			pages: algoliaBundle.Hogan.compile($('#autocomplete_pages_template').html()),
			additionalSection: algoliaBundle.Hogan.compile($('#autocomplete_extra_template').html())
		};
		
		/**
		 * Initialise Algolia client
		 * Docs: https://www.algolia.com/doc/api-client/getting-started/instantiate-client-index/
		 **/
		var algolia_client = algoliaBundle.algoliasearch(algoliaConfig.applicationId, algoliaConfig.apiKey);
		algolia_client.addAlgoliaAgent('Magento2 integration (' + algoliaConfig.extensionVersion + ')');
		
		/** Add products and categories that are required sections **/
		/** Add autocomplete menu sections **/
		if (algoliaConfig.autocomplete.nbOfProductsSuggestions > 0) {
			algoliaConfig.autocomplete.sections.unshift({ hitsPerPage: algoliaConfig.autocomplete.nbOfProductsSuggestions, label: algoliaConfig.translations.products, name: "products"});
		}
		
		if (algoliaConfig.autocomplete.nbOfCategoriesSuggestions > 0) {
			algoliaConfig.autocomplete.sections.unshift({ hitsPerPage: algoliaConfig.autocomplete.nbOfCategoriesSuggestions, label: algoliaConfig.translations.categories, name: "categories"});
		}
		
		if (algoliaConfig.autocomplete.nbOfQueriesSuggestions > 0) {
			algoliaConfig.autocomplete.sections.unshift({ hitsPerPage: algoliaConfig.autocomplete.nbOfQueriesSuggestions, label: '', name: "suggestions"});
		}
		
		/** Setup autocomplete data sources **/
		var sources = [],
			i = 0;
		$.each(algoliaConfig.autocomplete.sections, function (name, section) {
			var source = getAutocompleteSource(section, algolia_client, $, i);
			
			if (source) {
				sources.push(source);
			}
			
			/** Those sections have already specific placeholder, so do not use the default aa-dataset-{i} class **/
			if (section.name !== 'suggestions' && section.name !== 'products') {
				i++;
			}
		});
		
		/**
		 * Setup the autocomplete search input
		 * For autocomplete feature is used Algolia's autocomplete.js library
		 * Docs: https://github.com/algolia/autocomplete.js
		 **/
		$(algoliaConfig.autocomplete.selector).each(function (i) {
			var menu = $(this);
			var options = {
				hint: false,
				templates: {
					dropdownMenu: '#menu-template'
				},
				dropdownMenuContainer: "#algolia-autocomplete-container",
				debug: algoliaConfig.autocomplete.isDebugEnabled
			};
			
			if (isMobile() === true) {
				// Set debug to true, to be able to remove keyboard and be able to scroll in autocomplete menu
				options.debug = true;
			}
			
			if (algoliaConfig.removeBranding === false) {
				options.templates.footer = '<div class="footer_algolia"><a href="https://www.algolia.com/?utm_source=magento&utm_medium=link&utm_campaign=magento_autocompletion_menu" title="Search by Algolia" target="_blank"><img src="' +algoliaConfig.urls.logo + '"  alt="Search by Algolia" /></a></div>';
			}
			
			sources = algolia.triggerHooks('beforeAutocompleteSources', sources, algolia_client, algoliaBundle);
			options = algolia.triggerHooks('beforeAutocompleteOptions', options);
			
			// Keep for backward compatibility
			if (typeof algoliaHookBeforeAutocompleteStart === 'function') {
				console.warn('Deprecated! You are using an old API for Algolia\'s front end hooks. ' +
					'Please, replace your hook method with new hook API. ' +
					'More information you can find on https://community.algolia.com/magento/doc/m2/frontend-events/');
				
				var hookResult = algoliaHookBeforeAutocompleteStart(sources, options, algolia_client);
				
				sources = hookResult.shift();
				options = hookResult.shift();
			}
			
			/** Bind autocomplete feature to the input */
			$(this)
				.autocomplete(options, sources)
				.parent()
				.attr('id', 'algolia-autocomplete-tt')
				.on('autocomplete:updated', function (e) {
					fixAutocompleteCssSticky(menu);
				})
				.on('autocomplete:updated', function (e) {
					fixAutocompleteCssHeight(menu);
				}).on('autocomplete:selected', function (e, suggestion, dataset) {
					location.assign(suggestion.url);
				});
			
			$(window).resize(function () {
				fixAutocompleteCssSticky(menu);
			});
		});
		
		// Hack to handle buggy onclick event on iOS
		$(algoliaConfig.autocomplete.selector).each(function () {
			var data = $(this).data('aaAutocomplete');
			var dropdown = data.dropdown;
			var suggestionClass = '.' + dropdown.cssClasses.prefix + dropdown.cssClasses.suggestion;
			
			var touchmoved;
			dropdown.$menu.on('touchend', suggestionClass, function (e) {
				if(touchmoved === false) {
					e.preventDefault();
					e.stopPropagation();
					
					var url = $(this).find('a').attr('href');
					location.assign(url);
				}
			}).on('touchmove', function (){
				touchmoved = true;
			}).on('touchstart', function(){
				touchmoved = false;
			});
		});
	});
});
