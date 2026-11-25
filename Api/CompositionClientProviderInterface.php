<?php

namespace Algolia\AlgoliaSearch\Api;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;

interface CompositionClientProviderInterface extends ClientProviderInterface
{
    /**
     * @throws AlgoliaException
     */
    public function getClient(?int $storeId = self::ALGOLIA_DEFAULT_SCOPE): CompositionClient;
}
