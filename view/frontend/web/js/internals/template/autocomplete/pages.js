define([], function () {
    return {
        getNoResultHtml: function ({html}) {
            return html`<p>${algoliaConfig.translations.noResults}</p>`;
        },

        getHeaderHtml: function ({section}) {
            return section.label;
        },

        getItemHtml: function ({item, components, html}) {
            return html`<a class="algoliasearch-autocomplete-hit"
                           href="${item.url}"
                           data-objectId="${item.objectID}"
                           data-position="${item.position}"
                           data-index="${item.__autocomplete_indexName}"
                           data-queryId="${item.__autocomplete_queryID}">
                <div class="info-without-thumb">
                    ${this.safeHighlight(components, item, "name")}
                    <div class="details">
                        ${this.safeHighlight(components, item, "content")}
                    </div>
                </div>
                <div class="algolia-clearfix"></div>
            </a>`;
        },

        getFooterHtml: function () {
            return "";
        },

        safeHighlight: function(components, hit, attribute) {
            const highlightResult = hit._highlightResult[attribute];

            if (!highlightResult) return '';

            try {
                return components.Highlight({ hit, attribute });
            } catch (e) {
                return '';
            }
        }

    };
});
