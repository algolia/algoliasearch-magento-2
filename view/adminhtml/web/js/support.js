requirejs(['algoliaAdminBundle'], function(algoliaBundle) {
	algoliaBundle.$(function ($) {
		handleLatestVersion($);
		
		if ($('#search_box').length > 0) {
			initDocSearch();
			initDiscourseSearch();
		}
		
		if ($('.algolia_contact_form #subject').length > 0) {
			initContactDocSearch();
		}
		
		function initDocSearch() {
			const documentationSearch = algoliaBundle.instantsearch({
				appId: 'BH4D9OD16A',
				apiKey: '8e4f2bbd342d977574767948ca5b5c8a',
				indexName: 'magento2_algolia_support_page',
				searchParameters: {
					filters: 'NOT tags:m1',
					hitsPerPage: 10
				},
				searchFunction: searchFunction
			});
			
			documentationSearch.addWidget(getSearchBoxWidget(false));
			
			documentationSearch.addWidget(
				algoliaBundle.instantsearch.widgets.hits({
					container: '.doc.links',
					templates: {
						item: getDocumentationTemplate(),
						empty: 'No results. Please change your search query or visit <a href="https://community.algolia.com/magento/doc/m2/getting-started/?utm_source=magento&utm_medium=extension&utm_campaign=support-page" target="_blank">documentation</a>.'
					}
				})
			);
			
			documentationSearch.addWidget(
				algoliaBundle.instantsearch.widgets.stats({
					container: '.doc.stats',
					templates: {
						body: '{{nbHits}} results'
					}
				})
			);
			
			documentationSearch.addWidget(
				algoliaBundle.instantsearch.widgets.stats({
					container: '.doc.footer',
					transformData: function(hit) {
						hit['morePages'] = hit.nbPages > 1;
						
						return hit;
					},
					templates: {
						body: `
					{{#morePages}}
				        <a href="https://community.algolia.com/magento/doc/m2/getting-started/?utm_source=magento&utm_medium=extension&utm_campaign=support-page" class="footer" target="_blank">
				            See all documentation ...
				        </a>
					{{/morePages}}
					`
					}
				})
			);
			
			documentationSearch.start();
		}
		
		function initDiscourseSearch() {
			const discourseSearch = algoliaBundle.instantsearch({
				appId: 'G25OKIW19Q',
				apiKey: '7650ddf6ecb983c7cf3296c1aa225d0a',
				indexName: 'discourse-posts_magento_support_page',
				searchParameters: {
					filters: 'topic.tags: magento',
					hitsPerPage: 10
				},
				searchFunction: searchFunction
			});
			
			discourseSearch.addWidget(getSearchBoxWidget(true));
			
			discourseSearch.addWidget(
				algoliaBundle.instantsearch.widgets.hits({
					container: '.links.forum',
					templates: {
						item: getDiscourseTemplate(),
						empty: 'No results. Please change your search query or visit the <a href="https://discourse.algolia.com/tags/magento2/?utm_source=magento&utm_medium=extension&utm_campaign=support-page" target="_blank">forum</a>.'
					},
					transformData: {
						item: function(hit) {
							hit.content = escapeHighlightedString(
								hit._snippetResult.content.value
							);
							
							hit.tags = hit._highlightResult.topic.tags;
							
							return hit;
						}
					}
				})
			);
			
			discourseSearch.addWidget(
				algoliaBundle.instantsearch.widgets.stats({
					container: '.forum.stats',
					templates: {
						body: '{{nbHits}} results'
					}
				})
			);
			
			discourseSearch.addWidget(
				algoliaBundle.instantsearch.widgets.stats({
					container: '.forum.footer',
					transformData: function(hit) {
						hit['morePages'] = hit.nbPages > 1;
						
						return hit;
					},
					templates: {
						body: `
					{{#morePages}}
				        <a href="https://discourse.algolia.com/tags/magento2/?utm_source=magento&utm_medium=extension&utm_campaign=support-page" class="footer" target="_blank">
				            See all community forum ... ...
				        </a>
					{{/morePages}}
					`
					}
				})
			);
			
			discourseSearch.on('render', function() {
				// Loop over every containers
				document.querySelectorAll(".ais-hits--item a").forEach(container => {
					const content = container.querySelector('.content');
					
					// 186 - 3 lines, each line 62 chars max
					if (content.textContent.replace(/\s/g, "").length >= 186) {
						
						
						let style = document.createElement("style");
						style.innerHTML = `
							#${container.id} .content:after {
								display: block;
								content: "...";
								position: absolute;
								bottom: 0;
								right: 0;
								background:#FFF;
								padding: 0 0.5ch;
							}`;
							
						// And then insert this before the current container
						container.parentNode.insertAdjacentElement("afterbegin", style);
					}
				});
				
			});
			
			discourseSearch.start();
		}
		
		function initContactDocSearch() {
			const documentationSearch = algoliaBundle.instantsearch({
				appId: 'BH4D9OD16A',
				apiKey: '8e4f2bbd342d977574767948ca5b5c8a',
				indexName: 'magento2_algolia_support_page',
				searchParameters: {
					filters: 'NOT tags:m1',
					hitsPerPage: 3
				}
			});
			
			documentationSearch.addWidget(
				algoliaBundle.instantsearch.widgets.searchBox({
					container: '#subject',
					placeholder: '',
					reset: false,
					magnifier: false
				})
			);
			
			documentationSearch.addWidget(
				algoliaBundle.instantsearch.widgets.hits({
					container: '.contact_results',
					templates: {
						item: getDocumentationTemplate(),
						empty: 'No results. Please change your search query or visit <a href="https://community.algolia.com/magento/doc/m2/getting-started/?utm_source=magento&utm_medium=extension&utm_campaign=support-page" target="_blank">documentation</a>.'
					}
				})
			);
			
			documentationSearch.start();
		}
		
		function searchFunction(helper) {
			const $results = $('#results');
			const $landing = $('#landing');
			
			if (helper.state.query === '') {
				$results.hide();
				$landing.show();
				
				return;
			}
			
			helper.search();
			
			$results.show();
			$landing.hide();
		}
	});
	
	function handleLatestVersion($) {
		$.getJSON('https://api.github.com/repos/algolia/algoliasearch-magento-2/releases/latest', function(payload) {
			const latestVersion = payload.name;
			
			if(compareVersions(algoliaSearchExtentionsVersion, latestVersion) > 0) {
				$('#current_version').text(algoliaSearchExtentionsVersion);
				$('#latest_version').text(latestVersion);
				
				$('.legacy_version').show();
			}
		});
	}
	
	function getSearchBoxWidget(showIcons = false) {
		return algoliaBundle.instantsearch.widgets.searchBox({
			container: '#search_box',
			placeholder: 'Start typing your topic, i.e. indexing',
			reset: showIcons,
			magnifier: showIcons
		});
	}
	
	function getDocumentationTemplate() {
		return `
			<a href="{{url}}?utm_source=magento&utm_medium=extension&utm_campaign=support-page" target="_blank" id="doc_{{objectID}}">
				<span class="heading">
				{{#hierarchy.lvl0}}
					{{{_highlightResult.hierarchy.lvl0.value}}}
				{{/hierarchy.lvl0}}
				
				{{#hierarchy.lvl1}}
					> {{{_highlightResult.hierarchy.lvl1.value}}}
				{{/hierarchy.lvl1}}
				
				{{#hierarchy.lvl2}}
					 > {{{_highlightResult.hierarchy.lvl2.value}}}
				{{/hierarchy.lvl2}}
				
				{{#hierarchy.lvl3}}
					> {{{_highlightResult.hierarchy.lvl3.value}}}
				{{/hierarchy.lvl3}}
				
				{{#hierarchy.lvl4}}
					> {{{_highlightResult.hierarchy.lvl4.value}}}
				{{/hierarchy.lvl4}}
			</span>
			
			<span class="content">
				{{{#content}}}
					{{{_highlightResult.content.value}}}
				{{{/content}}}
			</span>
		</div>`;
	}
	
	function getDiscourseTemplate() {
		return `
			<a href="https://discourse.algolia.com{{url}}/?utm_source=magento&utm_medium=extension&utm_campaign=support-page" target="_blank" id="disc_{{objectID}}">
				<span class="heading">
					{{{ _highlightResult.topic.title.value }}}
					<img width="12" height="12" src="{{external_link_src}}">
				</span>
				
				<span class="content">
					{{{content}}}
				</span>
			</a>`;
	}
	
	function escapeHighlightedString(str, highlightPreTag, highlightPostTag) {
		highlightPreTag = highlightPreTag || '<em>';
		let pre = document.createElement('div');
		pre.appendChild(document.createTextNode(highlightPreTag));
		
		highlightPostTag = highlightPostTag || '</em>';
		let post = document.createElement('div');
		post.appendChild(document.createTextNode(highlightPostTag));
		
		let div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		
		return div.innerHTML
			.replace(RegExp(escapeRegExp(pre.innerHTML), 'g'), highlightPreTag)
			.replace(RegExp(escapeRegExp(post.innerHTML), 'g'), highlightPostTag)
	}
	
	function escapeRegExp(str) {
		return str.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, '\\$&');
	}
	
	function compareVersions(left, right) {
		left = sanitizeVersion(left);
		right = sanitizeVersion(right);
		
		for (let i = 0; i < Math.max(left.length, right.length); i++) {
			if (left[i] > right[i]) {
				return -1;
			}
			if (left[i] < right[i]) {
				return 1;
			}
		}
		
		return 0;
	}
	
	function sanitizeVersion(input) {
		return input.split('.').map(function (n) {
			return parseInt(n, 10);
		});
	}
});
