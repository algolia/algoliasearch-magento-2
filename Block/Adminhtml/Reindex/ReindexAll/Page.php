<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Reindex\ReindexAll;

use Algolia\AlgoliaSearch\Block\Adminhtml\Reindex\AbstractReindexAllButton;

class Page extends AbstractReindexAllButton
{
    protected string $entity = "pages";

    protected string $redirectPath = "cms/page/index";
}
