requirejs(['algoliaBundle', 'algoliaAnalytics'], function (algoliaBundle, algoliaAnalytics) {
	algoliaBundle.$(function ($) {
		algoliaAnalytics.init({
			appId: algoliaConfig.applicationId,
			apiKey: algoliaConfig.apiKey
		});
		var userAgent = 'insights-js-in-magento (' + window.algoliaConfig.extensionVersion + ')';
		algoliaAnalytics.addAlgoliaAgent(userAgent);

		// "Click" in autocomplete
		$(algoliaConfig.autocomplete.selector).each(function () {
			$(this).on('autocomplete:selected', function (e, suggestion) {
				trackClick(suggestion.objectID, suggestion.__position, suggestion.__queryID, suggestion.__indexName);
			});
		});

		// "Click" on instant search page
		$(document).on('click', algoliaConfig.ccAnalytics.ISSelector, function () {
			var $this = $(this);

			trackClick($this.data('objectid'), $this.data('position'), $this.data('queryid'), $this.data('indexname'));
		});

		// "Add to cart" conversion
		if (algoliaConfig.ccAnalytics.conversionAnalyticsMode === 'add_to_cart') {
			function getQueryParamFromCurrentUrl(queryParamName) {
				var url = window.location.href;
				var regex = new RegExp('[?&]' + queryParamName + '(=([^&#]*)|&|#|$)');
				var results = regex.exec(url);
				if (!results || !results[2]) return '';
				return results[2];
			}

			$(document).on('click', algoliaConfig.ccAnalytics.addToCartSelector, function () {
				var objectId = $(this).data('objectid') || getQueryParamFromCurrentUrl('indexName');
				var queryID = $(this).data('queryid') || getQueryParamFromCurrentUrl('queryID');
				var indexName = $(this).data('queryid') || getQueryParamFromCurrentUrl('indexName');

				// FIXME: what is this code for? removing it for now
				// if (!objectId) {
				// 	var postData = $(this).data('post');
				// 	if (!postData || !postData.data.product) {
				// 		return;
				// 	}
				//
				// 	objectId = postData.data.product;
				// }

				trackConversion(objectId, queryID, indexName);
			});
		}


		if (algoliaConfig.ccAnalytics.conversionAnalyticsMode === 'place_order') {

			if (typeof algoliaOrderConversionJson !== 'undefined') {
				$.each(algoliaOrderConversionJson, function(idx, itemData) {
					if (itemData !== null && typeof itemData.queryID !== 'undefined') {
						trackConversion(itemData.objectID, itemData.queryID, itemData.indexName);
					}
				});
			}
		}

	});

	algolia.registerHook('beforeInstantsearchInit', function (instantsearchOptions) {
		instantsearchOptions.searchParameters['clickAnalytics'] = true;

		return instantsearchOptions;
	});

	function trackClick(objectID, position, queryID, indexName) {
		var clickData = {
			eventName: 'Clicked',
			objectIDs: [objectID + ''],
			positions: [parseInt(position)],
			index: indexName,
		};

		if (queryID) {
			clickData.queryID = queryID;
			algoliaAnalytics.clickedObjectIDsAfterSearch(clickData);
		} else {
			algoliaAnalytics.clickedObjectIDs(clickData);
		}
	}

	function trackConversion(objectID, queryID, indexName) {
		var conversionData = {
			eventName: 'Added to cart',
			objectIDs: [objectID + ''],
			index: indexName
		};

		if (queryID) {
			conversionData.queryID = queryID;
			algoliaAnalytics.convertedObjectIDsAfterSearch(conversionData);
		} else {
			algoliaAnalytics.convertedObjectIDs(conversionData);
		}
	}
});
