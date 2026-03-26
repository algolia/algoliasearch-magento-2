<?php

namespace Algolia\AlgoliaSearch\Api;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;

interface SearchClientProviderInterface
{
    /**
     * @throws AlgoliaException
     */
    public function getClient(?int $storeId = 0): SearchClient;
}
