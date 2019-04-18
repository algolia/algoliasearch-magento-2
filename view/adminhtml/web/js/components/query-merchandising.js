define(
	[
		'underscore',
		'uiComponent',
		'ko',
		'jquery',
		'mage/translate',
	],
	function (_, Component, ko, $, $t) {
		'use strict';

		return Component.extend(
			{
				defaults: {
					queryText: '',
					storeId: '',
					imports: {
						queryText: '${$.provider}:${$.dataScope}.query_text',
						storeId: '${$.provider}:${$.dataScope}.store_id',
					},
				},

				initialize: function () {
					var self = this;
					this._super();
					$( document ).ready(function() {
					    self.initSubscribers();
					});
					
				},

				initObservable: function () {
					this._super().observe(
						'queryText storeId'
					);
					return this;
				},

				initSubscribers: function () {
					var self = this;
					self.queryText.subscribe(
						function (queryText) {
							if (typeof window.algoliaSearch != "undefined") {
								window.algoliaSearch.helper.setQuery(queryText).search();
							}
						}
					); 
					self.storeId.subscribe(
						function (storeId) {
							if (typeof window.algoliaSearch != "undefined") {
								window.algoliaSearch.helper.setIndex(window.algoliaSearchConfig.indexDataByStoreIds[storeId].indexName + '_products').search();
							}
						}
					);          
				},
			}
		);
	}
);