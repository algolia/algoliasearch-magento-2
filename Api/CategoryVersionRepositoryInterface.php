<?php

namespace Algolia\AlgoliaSearch\Api;

use Algolia\AlgoliaSearch\Api\Data\CategoryVersionInterface;
use Algolia\AlgoliaSearch\Api\Data\CategoryVersionSearchResultsInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Api\SearchCriteriaInterface;

interface CategoryVersionRepositoryInterface
{
    /**
     * @param int $id
     * @return CategoryVersionInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $id): CategoryVersionInterface;

    /**
     * @return CategoryVersionInterface
     */
    public function getNew(): CategoryVersionInterface;

    /**
     * @param CategoryVersionInterface $version
     * @return CategoryVersionInterface
     * @throws AlreadyExistsException
     */
    public function save(CategoryVersionInterface $version): CategoryVersionInterface;

    /**
     * @param SearchCriteriaInterface|null $searchCriteria
     * @return CategoryVersionSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria = null): CategoryVersionSearchResultsInterface;
}
