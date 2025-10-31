<?php

namespace Algolia\AlgoliaSearch\Api\Data;

interface PaginationInfoInterface
{
    public function getPageNumber(): int;
    public function getPageSize(): int;
    public function getOffset(): int;

    public function setPageNumber(int $pageNumber): PaginationInfoInterface;
    public function setPageSize(int $pageSize): PaginationInfoInterface;
    public function setOffset(int $offset): PaginationInfoInterface;
}
