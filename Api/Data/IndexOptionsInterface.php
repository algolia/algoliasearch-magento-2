<?php

namespace Algolia\AlgoliaSearch\Api\Data;

interface IndexOptionsInterface
{
    public const STORE_ID = 'store_id';

    public const INDEX_SUFFIX = 'index_suffix';

    public const IS_TMP = 'is_tmp';

    public const INDEX_NAME = 'index_name';

    /**
     * Get field: store_id
     *
     */
    public function getStoreId(): ?int;

    /**
     * Get field: index_suffix
     *
     */
    public function getIndexSuffix(): ?string;

    /**
     * Get field: is_tmp
     *
     */
    public function isTemporaryIndex(): bool;

    /**
     * Get field: index_name
     *
     */
    public function getIndexName(): ?string;

    /**
     * Set field: index_name
     *
     */
    public function setIndexName(string $indexName): void;
}
