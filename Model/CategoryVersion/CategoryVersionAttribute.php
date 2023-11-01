<?php

namespace Algolia\AlgoliaSearch\Model\CategoryVersion;

use Algolia\AlgoliaSearch\Api\CategoryVersionRepositoryInterface;
use Algolia\AlgoliaSearch\Api\Data\CategoryVersionAttributeInterface;
use Algolia\AlgoliaSearch\Api\Data\CategoryVersionInterface;
use Algolia\AlgoliaSearch\Api\Data\CategoryVersionSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CategoryVersionAttribute implements CategoryVersionAttributeInterface
{
    /** @var int|null */
    protected int|null $storeId;

    /** @var int */
    protected int $categoryId;

    /** @var array|null  */
    private array|null $versions;

    /** @var CategoryVersionRepositoryInterface  */
    protected CategoryVersionRepositoryInterface $categoryVersionRepository;

    /** @var SearchCriteriaBuilder  */
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    public function __construct(
        CategoryVersionRepositoryInterface $categoryVersionRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->categoryVersionRepository = $categoryVersionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->versions = null;
    }

    /**
     * @inheritDoc
     */
    public function load(int $categoryId, int $storeId = null) : CategoryVersionAttributeInterface
    {
        $this->categoryId = $categoryId;
        $this->storeId = $storeId;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasVersions(int $storeId = null): bool
    {
        $storeId ??= $this->storeId;
        if (!$storeId) return false;

        return (bool) count($this->getVersions($storeId));
    }

    /**
     * @inheritDoc
     */
    public function getVersions(int $storeId = null): array
    {
        $storeId ??= $this->storeId;
        if (!$storeId) return [];

        if (!$this->versions) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(CategoryVersionInterface::KEY_CATEGORY_ID, $this->categoryId)
                ->addFilter(CategoryVersionInterface::KEY_STORE_ID, $storeId);
            /* @var CategoryVersionSearchResultsInterface */
            $this->versions = array_values($this->categoryVersionRepository->getList($searchCriteria->create())->getItems());
        }

        return $this->versions;
    }

    /**
     * @inheritDoc
     */
    public function getSearchFilters(int $storeId = null): array
    {
        $storeId ??= $this->storeId;
        if (!$storeId) return [];

        return array_map(
            function(CategoryVersionInterface $version) {
                return $version->getOldValue();
            },
            $this->getVersions($storeId)
        );
    }
}
