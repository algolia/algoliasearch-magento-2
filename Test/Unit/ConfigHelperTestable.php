<?php

namespace Algolia\AlgoliaSearch\Test\Unit;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;

class ConfigHelperTestable extends ConfigHelper
{
    /** expose protected methods for unit testing */
    public function serialize(array $value): string
    {
        return parent::serialize($value);
    }
}
