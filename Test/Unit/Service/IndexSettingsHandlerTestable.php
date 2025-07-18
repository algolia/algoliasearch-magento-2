<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Service\IndexSettingsHandler;

class IndexSettingsHandlerTestable extends IndexSettingsHandler
{
    public function splitSettings(...$params): array
    {
        return parent::splitSettings(...$params);
    }
}
