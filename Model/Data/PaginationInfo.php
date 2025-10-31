<?php

namespace Algolia\AlgoliaSearch\Model\Data;

use Algolia\AlgoliaSearch\Api\Data\PaginationInfoInterface;

class PaginationInfo implements PaginationInfoInterface
{
    /** @var int  */
    public const DEFAULT_PAGE_SIZE = 9;

    public function __construct(
        protected int $pageNumber = 1,
        protected int $pageSize = self::DEFAULT_PAGE_SIZE,
        protected int $offset = 0,
    ) {}

    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setPageNumber(int $pageNumber): PaginationInfoInterface
    {
        $this->pageNumber = $pageNumber;
        return $this;
    }

    public function setPageSize(int $pageSize): PaginationInfoInterface
    {
        $this->pageSize = $pageSize;
        $this->recalculateOffset(); // changes to the page size impact the offset (this allows for a "smart" offset)
        return $this;
    }

    protected function recalculateOffset(): int
    {
        $offset = ($this->getPageNumber() - 1) * $this->getPageSize();
        $this->offset = $offset;
        return $offset;
    }

    public function setOffset(int $offset): PaginationInfoInterface
    {
        $this->offset = $offset;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'pageNumber' => $this->getPageNumber(),
            'pageSize' => $this->getPageSize(),
            'offset' => $this->getOffset(),
        ];
    }
}
