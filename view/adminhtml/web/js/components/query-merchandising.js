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
						'queryText'
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
				},
			}
		);
	}
);
