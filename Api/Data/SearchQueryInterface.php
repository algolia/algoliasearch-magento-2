<?php

namespace Algolia\AlgoliaSearch\Api\Data;

interface SearchQueryInterface
{
    /**
     * Get the query string
     */
    public function getQuery(): string;

    /**
     * Set the query string
     */
    public function setQuery(string $query): void;

    /**
     * Get the search parameters
     */
    public function getParams(): array;

    /**
     * Set the parameters
     */
    public function setParams(array $params): void;

    /**
     * Get the index options
     */
    public function getIndexOptions(): IndexOptionsInterface;

    /**
     * Set the index options
     */
    public function setIndexOptions(IndexOptionsInterface $indexOptions): void;
}
