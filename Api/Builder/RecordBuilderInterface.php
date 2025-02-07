<?php

namespace Algolia\AlgoliaSearch\Api\Builder;

use Magento\Framework\DataObject;

interface RecordBuilderInterface
{
    public function buildRecord(DataObject $entity): array;
}
