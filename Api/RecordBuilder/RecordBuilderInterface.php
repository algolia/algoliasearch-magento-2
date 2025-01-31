<?php

namespace Algolia\AlgoliaSearch\Api\RecordBuilder;

use Magento\Framework\DataObject;

interface RecordBuilderInterface
{
    public function buildRecord(DataObject $entity): array;
}
