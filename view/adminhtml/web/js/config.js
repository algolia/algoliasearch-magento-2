require(
	[
		'jquery',
		'mage/translate',
	],
	function ($) {

		addDashboardWarnings();

		function addDashboardWarnings() {
			// rows
			const rowIds = [
				'#row_algoliasearch_instant_instant_facets_facets',
				'#row_algoliasearch_instant_instant_facets_max_values_per_facet'
			];

			let rowWarning = '<div class="algolia_dashboard_warning">';
			rowWarning += '<p>This setting is also available in the Algolia Dashboard. We advise you to manage it from this page, because saving Magento settings will override the Algolia settings.</p>';
			rowWarning += '</div>';

			for (let i=0; i < rowIds.length; i++) {
				const element = $(rowIds[i]);
				if (element.length > 0) {
					element.find('.value').prepend(rowWarning);
				}
			}

			// pages
			const pageIds = [
				'#algoliasearch_products_products',
				'#algoliasearch_categories_categories',
				'#algoliasearch_credentials_credentials',
				'#algoliasearch_extra_settings_extra_settings'
			];

			let pageWarning = '<div class="algolia_dashboard_warning algolia_dashboard_warning_page">';
			pageWarning += '<p>These settings are also available in the Algolia Dashboard. We advise you to manage it from this page, cause saving Magento settings will override the Algolia settings.</p>';
			pageWarning += '</div>';

			let pageWarningSynonyms = '<div class="algolia_dashboard_warning algolia_dashboard_warning_page">';
			pageWarningSynonyms += '<p>Configurations related to Synonyms have been removed from the Magento dashboard. We advise you to configure synonyms from the Algolia dashboard.</p>';
			pageWarningSynonyms += '</div>';

			for (let i=0; i < pageIds.length; i++) {
				const element = $(pageIds[i]);
				if (element.length > 0 && pageIds[i] != "#algoliasearch_credentials_credentials") {
					element.find('.comment').append(pageWarning);
				} else if (element.length > 0 && pageIds[i] == "#algoliasearch_credentials_credentials"){
				    element.find('.comment').append(pageWarningSynonyms);
				}
			}
		}

		if ($('#algoliasearch_instant_instant_facets_facets').length > 0) {
			const addButton = $('#algoliasearch_instant_instant_facets tfoot .action-add');
			addButton.on('click', function(){
				handleFacetQueryRules();
			});

			handleFacetQueryRules();
		}

		function handleFacetQueryRules() {
			const facets = $('#algoliasearch_instant_instant_facets_facets tbody tr');

			for (let i=0; i < facets.length; i++) {
				const rowId = $(facets[i]).attr('id');
				const searchableSelect = $('select[name="groups[instant_facets][fields][facets][value][' + rowId + '][searchable]"]');

				searchableSelect.on('change', function(){
					configQrFromSearchableSelect($(this));	
				});

				configQrFromSearchableSelect(searchableSelect);
			}
		}

		function configQrFromSearchableSelect(searchableSelect) {
			const rowId = searchableSelect.parent().parent().attr('id');
			const qrSelectId = 'select[name="groups[instant_facets][fields][facets][value][' + rowId + '][create_rule]"]';
			const qrSelect = $(qrSelectId);
			if (qrSelect.length > 0) {
				if (searchableSelect.val() == "2") {
					qrSelect.val('2');
					qrSelect.attr('readonly', 'readonly');
				} else {
					qrSelect.removeAttr('readonly');
				}
			} else {
				$('#row_algoliasearch_instant_instant_facets_facets .algolia_block').hide();
			}
		}

	}
);	
