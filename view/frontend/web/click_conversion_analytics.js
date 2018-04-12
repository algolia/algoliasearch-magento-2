requirejs(['algoliaBundle', 'algoliaAnalytics'], function(algoliaBundle, algoliaAnalytics) {
	var objectIdsStorageKey = 'algoliasearch_analytics_ids';
	
	algoliaBundle.$(function ($) {
		algoliaAnalytics.init({
			applicationID: algoliaConfig.applicationId,
			apiKey: algoliaConfig.apiKey
		});
		
		// "Click" in autocomplete
		$(algoliaConfig.autocomplete.selector).each(function () {
			$(this).on('autocomplete:selected', function (e, suggestion) {
				algoliaAnalytics.click({
					queryID: suggestion.__queryID,
					objectID: suggestion.objectID,
					position: suggestion.__position
				});
			});
		});
		
		// "Click" on instant search page
		$(document).on('click', algoliaConfig.ccAnalytics.ISSelector, function() {
			var $this = $(this);
			
			trackClick($this.data('objectid'), $this.data('position'));
		});
		
		// "Add to cart" conversion
		if (algoliaConfig.ccAnalytics.conversionAnalyticsMode === 'add_to_cart') {
			$(document).on('click', algoliaConfig.ccAnalytics.addToCartSelector, function () {
				var objectId = $(this).data('objectid') || algoliaConfig.productId;
				
				setTimeout(function () {
					trackConversion(objectId);
				}, 0);
			});
		}
		
		// "Place order" conversion
		if (algoliaConfig.ccAnalytics.conversionAnalyticsMode === 'place_order') {
			$(document).on('click', algoliaConfig.ccAnalytics.placeOrderSelector, function () {
				$.each(algoliaConfig.ccAnalytics.productIdsInCart, function (i, objectId) {
					trackConversion(objectId);
				});
			});
		}
	});
	
	algolia.registerHook('beforeInstantsearchInit', function (instantsearchOptions) {
		instantsearchOptions.searchParameters['clickAnalytics'] = true;
		
		return instantsearchOptions;
	});
	
	algolia.registerHook('beforeInstantsearchStart', function (search) {
		search.once('render', function() {
			algoliaAnalytics.initSearch({
				getQueryID: function() {
					return search.helper.lastResults && search.helper.lastResults._rawResults[0].queryID
				}
			});
		});
		
		return search;
	});
	
	function trackClick(objectID, position) {
		objectID = objectID.toString();
		
		var clickedObjectIds = getObjectIds('clicked');
		if (!clickedObjectIds[objectID]) {
			algoliaAnalytics.click({
				objectID: objectID.toString(),
				position: parseInt(position)
			});
			
			clickedObjectIds[objectID] = 1;
			
			var convertedObjectIds = getObjectIds('converted');
			delete convertedObjectIds[objectID];
			
			setObjectIds('clicked', clickedObjectIds);
			setObjectIds('converted', convertedObjectIds);
		}
	}
	
	function trackConversion(objectID) {
		objectID = objectID.toString();
		
		var convertedObjectIds = getObjectIds('converted');
		if (!convertedObjectIds[objectID]) {
			algoliaAnalytics.conversion({
				objectID: objectID
			});
			
			convertedObjectIds[objectID] = 1;
			
			var clickedObjectIds = getObjectIds('clicked');
			delete clickedObjectIds[objectID];
			
			setObjectIds('clicked', clickedObjectIds);
			setObjectIds('converted', convertedObjectIds);
		}
	}
	
	function getObjectIds(type) {
		var objectIds = localStorage.getItem(objectIdsStorageKey + '_' + type);
		
		if (!objectIds) {
			return [];
		}
		
		return JSON.parse(objectIds);
	}
	
	function setObjectIds(type, objectIds) {
		localStorage.setItem(objectIdsStorageKey + '_' + type, JSON.stringify(objectIds));
	}
});
