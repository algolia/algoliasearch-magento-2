<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Plugin;

use Algolia\AlgoliaSearch\Plugin\RenderingCacheContextPlugin;

class RenderingCacheContextPluginTestable extends RenderingCacheContextPlugin
{
    public function isCategoryRoute(...$args): bool
    {
        return parent::isCategoryRoute(...$args);
    }

    public function getOriginalRoute(...$args): string
    {
        return parent::getOriginalRoute(...$args);
    }

    public function shouldApplyCacheContext(...$args): bool
    {
        return parent::shouldApplyCacheContext(...$args);
    }
}
