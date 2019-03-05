<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml\Merchandising;

use Algolia\AlgoliaSearch\Helper\ProxyHelper;

class Page {

    /** @var ProxyHelper */
    private $proxyHelper;

    public function __construct(
        ProxyHelper $proxyHelper
    ) {
        $this->proxyHelper = $proxyHelper;
    }
}
