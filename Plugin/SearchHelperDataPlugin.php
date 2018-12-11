<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Magento\Search\Helper\Data;

class SearchHelperDataPlugin
{
    // The method makes sure the rendered query on front-end is not "__empty__"
    public function afterGetEscapedQueryText(Data $subject, $result)
    {
        return $result === '__empty__' ? '' : $result;
    }
}
