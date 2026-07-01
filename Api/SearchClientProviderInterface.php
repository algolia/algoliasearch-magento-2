<?php

namespace Algolia\AlgoliaSearch\Api;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;

interface SearchClientProviderInterface extends ClientProviderInterface
{
    /**
     * @throws AlgoliaException
     */
    public function getClient(?int $storeId = self::ALGOLIA_DEFAULT_SCOPE): SearchClient;
}
