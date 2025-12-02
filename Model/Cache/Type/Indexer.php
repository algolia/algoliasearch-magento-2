<?php

namespace Algolia\AlgoliaSearch\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

class Indexer extends TagScope {
    const TYPE_IDENTIFIER = 'algolia_indexer';
    const CACHE_TAG = 'ALGOLIA_INDEXER';

    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct(
            $cacheFrontendPool->get(self::TYPE_IDENTIFIER),
            self::CACHE_TAG
        );
    }
}
