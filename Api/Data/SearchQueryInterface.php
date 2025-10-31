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
    public function setQuery(string $query): SearchQueryInterface;

    /**
     * Get the search parameters
     */
    public function getParams(): array;

    /**
     * Set the parameters
     */
    public function setParams(array $params): SearchQueryInterface;

    /**
     * Get the index options
     */
    public function getIndexOptions(): ?IndexOptionsInterface;

    /**
     * Set the index options
     */
    public function setIndexOptions(IndexOptionsInterface $indexOptions): SearchQueryInterface;

    /**
     * Get the pagination info
     */
    public function getPaginationInfo(): ?PaginationInfoInterface;

    /**
     * Set the pagination info
     */
    public function setPaginationInfo(PaginationInfoInterface $paginationInfo): SearchQueryInterface;
}
