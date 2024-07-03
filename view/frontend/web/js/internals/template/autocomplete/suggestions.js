define([], function () {
    return {
        getNoResultHtml: function ({html}) {
            return html`<p>${algoliaConfig.translations.noResults}</p>`;
        },

        getHeaderHtml: function ({html}) {
            return html`<p>${algoliaConfig.translations.suggestions}</p>`;
        },

        getItemHtml: function ({item, components, html}) {
            const itemQuery = (item._highlightResult?.query?.value)
                ? components.Highlight({ hit: item, attribute: "query" })
                : item.query;

            return html`<a class="aa-ItemLink algolia-suggestions algoliasearch-autocomplete-hit"
                           href="${algoliaConfig.resultPageUrl}?q=${encodeURIComponent(item.query)}"
                           data-objectId="${item.objectID}"
                           data-position="${item.position}"
                           data-index="${item.__autocomplete_indexName}"
                           data-queryId="${item.__autocomplete_queryID}">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="algolia-glass-suggestion magnifying-glass"
                     width="24"
                     height="24"
                     viewBox="0 0 128 128">
                    <g transform="scale(2.5)">
                        <path stroke-width="3" d="M19.5 19.582l9.438 9.438"></path>
                        <circle stroke-width="3" cx="12" cy="12" r="10.5" fill="none"></circle>
                        <path d="M23.646 20.354l-3.293 3.293c-.195.195-.195.512 0 .707l7.293 7.293c.195.195.512.195.707
                0l3.293-3.293c.195-.195.195-.512 0-.707l-7.293-7.293c-.195-.195-.512-.195-.707 0z" ></path>
                    </g>
                </svg>
                ${itemQuery}
            </a>`;
        },

        getFooterHtml: function () {
            return "";
        }
    };
});
