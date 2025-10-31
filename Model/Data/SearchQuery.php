<?php

namespace Algolia\AlgoliaSearch\Model\Data;

use Algolia\AlgoliaSearch\Api\Data\PaginationInfoInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterface;
use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;

class SearchQuery implements SearchQueryInterface
{

    public function __construct(
        protected string                   $query = "",
        protected array                    $params = [],
        protected ?IndexOptionsInterface   $indexOptions = null,
        protected ?PaginationInfoInterface $paginationInfo = null,
    ) {}

    /**
     * @inheritdoc
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @inheritdoc
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @inheritdoc
     */
    public function getIndexOptions(): IndexOptionsInterface
    {
        return $this->indexOptions;
    }

    /**
     * @inheritdoc
     */
    public function setQuery(string $query): SearchQueryInterface
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setParams(array $params): SearchQueryInterface
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setIndexOptions(IndexOptionsInterface $indexOptions): SearchQueryInterface
    {
        $this->indexOptions = $indexOptions;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPaginationInfo(): ?PaginationInfoInterface
    {
        return $this->paginationInfo;
    }

    /**
     * @inheritdoc
     */
    public function setPaginationInfo(PaginationInfoInterface $paginationInfo): SearchQueryInterface
    {
        $this->paginationInfo = $paginationInfo;
        return $this;
    }
}
