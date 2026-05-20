<?php

namespace Algolia\AlgoliaSearch\Model\Data;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterface;

class SearchQuery implements SearchQueryInterface
{

    public function __construct(
        protected ?IndexOptionsInterface $indexOptions = null,
        protected string                 $query = "",
        protected array                  $params = [],
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
    public function getIndexOptions(): ?IndexOptionsInterface
    {
        return $this->indexOptions;
    }

    /**
     * @inheritdoc
     */
    public function setQuery(string $query): self
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setParams(array $params): self
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setIndexOptions(IndexOptionsInterface $indexOptions): self
    {
        $this->indexOptions = $indexOptions;
        return $this;
    }
}
