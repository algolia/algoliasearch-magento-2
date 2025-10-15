<?php

namespace Algolia\AlgoliaSearch\Model\Data;

use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterface;
use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;

class SearchQuery implements SearchQueryInterface
{

    public function __construct(
        protected ?IndexOptionsInterface $indexOptions = null,
        protected string                 $query = "",
        protected array                  $params = []
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
    public function setQuery(string $query): void
    {
        $this->query = $query;
    }

    /**
     * @inheritdoc
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * @inheritdoc
     */
    public function setIndexOptions(IndexOptionsInterface $indexOptions): void
    {
        $this->indexOptions = $indexOptions;
    }
}
