<?php

namespace Algolia\AlgoliaSearch\Model\Config;

class PaginationMode extends AbstractConfigComment
{
    public function getCommentText($elementValue): string
    {
        $magentoProductGridConfigLink = $this->getConfigLink('catalog', 'catalog_frontend-link', true);

        return <<<COMMENT
             The number of products displayed on the InstantSearch results page. You can choose either to inherit from the <a href="$magentoProductGridConfigLink"><strong>Magento configuration</strong></a> or define a custom one.<br/>
            <strong>Magento Grid Pagination: </strong> see "Catalog > StoreFront > Products per Page on Grid Default Value"<br/>
            <strong>Magento List Pagination: </strong> see "Catalog > StoreFront > Products per Page on List Default Value"
            COMMENT;
    }
}
