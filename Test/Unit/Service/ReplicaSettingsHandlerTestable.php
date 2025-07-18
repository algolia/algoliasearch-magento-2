<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Service\ReplicaSettingsHandler;

class ReplicaSettingsHandlerTestable extends ReplicaSettingsHandler
{
    public function splitSettings(...$params): array
    {
        return parent::splitSettings(...$params);
    }
}
