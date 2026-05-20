<?php

namespace Algolia\AlgoliaSearch\Model\Config;

class AttributesComment extends AbstractConfigComment
{
    public function getCommentText($elementValue): string
    {
        $facetsConfigLink = $this->getConfigLink('algoliasearch_instant', 'algoliasearch_instant_instant_facets-link');
        $sortsConfigLink = $this->getConfigLink('algoliasearch_instant', 'algoliasearch_instant_instant_sorts-link');

        return <<<COMMENT
            Specify a product's attributes your users can search on (searchable) and the ones required to display search results.
            The order of the searchable attributes matters: a query matching the first searchable attribute of a product will put this product before the others in the results.<br><br>
            Searchable attributes' documentation: <a target="_blank" href="https://www.algolia.com/doc/integration/magento-2/how-it-works/indexing/?utm_source=magento&utm_medium=extension&utm_campaign=magento_2&utm_term=shop-owner&utm_content=doc-link#searchable-attributes">Products' searchable attributes</a>
            <br><span class="algolia-config-warning">&#9888;</span> Do not forget to reindex the Algolia Search Products indexer after you've modified this panel.
            <div class="algolia_dashboard_warning algolia_dashboard_warning_page">
            <p>You can also find <a href="$facetsConfigLink" target="_blank">Facets configuration</a> and <a href="$sortsConfigLink" target="_blank">Sorting configuration</a> in the "InstantSearch Result Page" section.</p>
            <br>
            </div>
            COMMENT;
    }
}
