<?php

namespace Algolia\AlgoliaSearch\Model\Config;

use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;

class SortParam extends AbstractConfigComment
{
    public function __construct(
        protected RequestInterface $request,
        protected UrlInterface     $urlInterface,
        protected IndexNameFetcher $indexNameFetcher
    ){
        parent::__construct($request, $urlInterface);
    }

    public function getCommentText($elementValue): string
    {
        $productIndex = $this->indexNameFetcher->getIndexName('_products') . '_price_default_asc';

        return <<<COMMENT
             The sort parameter you want to use in you urls. <br/><br/>
            <strong>- sortBy (Algolia default): </strong> (example: http//mywebsite.com/?<strong>sortBy</strong>=$productIndex&<strong>page</strong>=2)<br/>
            The "sortBy" parameter will be associated to an Algolia replica index.<br/>
            The pagination parameter is the InstantSearch default "page".<br/>
            <br/>
            <strong>- product_list_order (Magento default): </strong>(example: http//mywebsite.com/?<strong>product_list_order</strong>=price~asc&<strong>p</strong>=2)<br/>
            The "product_list_order" parameter will be associated to "sort~direction" pair and will replicate the default Magento urls.<br/>
            The pagination parameter is the Magento default "p".<br/>
            COMMENT;
    }
}
