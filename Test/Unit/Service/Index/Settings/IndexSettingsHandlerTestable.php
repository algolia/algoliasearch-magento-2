<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Index\Settings;

use Algolia\AlgoliaSearch\Service\Index\Settings\IndexSettingsHandler;

class IndexSettingsHandlerTestable extends IndexSettingsHandler
{
    public function splitSettings(...$params): array
    {
        return parent::splitSettings(...$params);
    }
}
