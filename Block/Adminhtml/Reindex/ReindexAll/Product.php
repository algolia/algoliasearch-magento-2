<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Reindex\ReindexAll;

use Algolia\AlgoliaSearch\Block\Adminhtml\Reindex\AbstractReindexAllButton;

class Product extends AbstractReindexAllButton
{
    protected string $entity = "products";

    protected string $redirectPath = "catalog/product/index";
}
