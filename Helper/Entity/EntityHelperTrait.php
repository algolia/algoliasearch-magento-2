<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

trait EntityHelperTrait
{
    public function getIndexNameSuffix(): string
    {
        return self::INDEX_NAME_SUFFIX;
    }
}
