<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Controller\Adminhtml\IndexingManager;

use Algolia\AlgoliaSearch\Controller\Adminhtml\IndexingManager\Reindex;

class ReindexTestable extends Reindex
{
    public function defineEntitiesToIndex(array $params): array
    {
        return parent::defineEntitiesToIndex($params);
    }

    public function defineRedirectPath(array $params): string
    {
        return parent::defineRedirectPath($params);
    }
}
