require(
	[
		'jquery',
		'mage/translate',
	],
	function ($) {

		addDashboardWarnings();

		function addDashboardWarnings() {
			// rows
			var rowIds = [
				'#row_algoliasearch_instant_instant_facets',
				'#row_algoliasearch_instant_instant_max_values_per_facet'
			];

			var rowWarning = '<div class="algolia_dashboard_warning">';
			rowWarning += '<p>This setting is also available in the Algolia Dashboard. We advise you to manage it from this page, because saving Magento settings will override the Algolia settings.</p>';
			rowWarning += '</div>';

			for (var i=0; i < rowIds.length; i++) {
				var element = $(rowIds[i]);
				if (element.length > 0) {
					element.find('.value').prepend(rowWarning);
				}
			}

			// pages
			var pageIds = [
				'#algoliasearch_products_products',
				'#algoliasearch_categories_categories',
				'#algoliasearch_synonyms_synonyms_group',
				'#algoliasearch_extra_settings_extra_settings'
			];

			var pageWarning = '<div class="algolia_dashboard_warning algolia_dashboard_warning_page">';
			pageWarning += '<p>These settings are also available in the Algolia Dashboard. We advise you to manage it from this page, because saving Magento settings will override the Algolia settings.</p>';
			pageWarning += '</div>';

			for (var i=0; i < pageIds.length; i++) {
				var element = $(pageIds[i]);
				if (element.length > 0) {
					element.find('.comment').append(pageWarning);
				}
			}
		}

		if ($('#algoliasearch_instant_instant_facets').length > 0) {
			var addButton = $('#algoliasearch_instant_instant_facets tfoot .action-add');
			addButton.on('click', function(){
				handleFacetQueryRules();
			});

			handleFacetQueryRules();
		}

		function handleFacetQueryRules() {
			var facets = $('#algoliasearch_instant_instant_facets tbody tr');

			for (var i=0; i < facets.length; i++) {
				var rowId = $(facets[i]).attr('id');
				var searchableSelect = $('select[name="groups[instant][fields][facets][value][' + rowId + '][searchable]"]');

				searchableSelect.on('change', function(){
					configQrFromSearchableSelect($(this));	
				});

				configQrFromSearchableSelect(searchableSelect);	
			}
		}

		function configQrFromSearchableSelect(searchableSelect) {
			var rowId = searchableSelect.parent().parent().attr('id');
			var qrSelect = $('select[name="groups[instant][fields][facets][value][' + rowId + '][create_rule]"]');
			if (qrSelect.length > 0) {
				if (searchableSelect.val() == "2") {
					qrSelect.val('2');
					qrSelect.attr('disabled','disabled');
				} else {
					qrSelect.removeAttr('disabled');
				}
			} else {
				$('#row_algoliasearch_instant_instant_facets .algolia_block').hide();
			}
		}

	}
);	
