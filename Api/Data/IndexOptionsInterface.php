<?php

namespace Algolia\AlgoliaSearch\Api\Data;

interface IndexOptionsInterface
{
    const STORE_ID = 'store_id';

    const INDEX_SUFFIX = 'index_suffix';

    const IS_TMP = 'is_tmp';

    const INDEX_NAME = 'index_name';

    /**
     * Get field: store_id
     *
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * Get field: index_suffix
     *
     * @return string|null
     */
    public function getIndexSuffix(): ?string;

    /**
     * Get field: is_tmp
     *
     * @return bool
     */
    public function isTemporaryIndex(): bool;

    /**
     * Get field: index_name
     *
     * @return string|null
     */
    public function getIndexName(): ?string;

    /**
     * Set field: index_name
     *
     * @param string $indexName
     * @return void
     */
    public function setIndexName(string $indexName): void;
}
