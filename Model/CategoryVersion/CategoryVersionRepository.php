<?php

namespace Algolia\AlgoliaSearch\Model\CategoryVersion;

use Algolia\AlgoliaSearch\Api\CategoryVersionRepositoryInterface;
use Algolia\AlgoliaSearch\Api\Data\CategoryVersionInterface;
use Algolia\AlgoliaSearch\Api\Data\CategoryVersionInterfaceFactory;
use Algolia\AlgoliaSearch\Api\Data\CategoryVersionSearchResultsInterface;
use Algolia\AlgoliaSearch\Api\Data\CategoryVersionSearchResultsInterfaceFactory;
use Algolia\AlgoliaSearch\Model\ResourceModel\CategoryVersion as CategoryVersionResource;
use Algolia\AlgoliaSearch\Model\ResourceModel\CategoryVersion\CollectionFactory as CategoryVersionCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

class CategoryVersionRepository implements CategoryVersionRepositoryInterface
{
    /** @var array */
    protected array $versions;

    /** @var CategoryVersionInterfaceFactory */
    protected CategoryVersionInterfaceFactory $versionFactory;

    /** @var CategoryVersionResource */
    protected CategoryVersionResource $versionResource;

    /** @var CategoryVersionSearchResultsInterfaceFactory */
    protected CategoryVersionSearchResultsInterfaceFactory $searchResultsFactory;

    /** @var CategoryVersionCollectionFactory */
    protected CategoryVersionCollectionFactory $categoryVersionCollectionFactory;

    /** @var CollectionProcessorInterface */
    protected CollectionProcessorInterface $collectionProcessor;

    public function __construct(
        CategoryVersionInterfaceFactory              $versionFactory,
        CategoryVersionResource                      $versionResource,
        CategoryVersionSearchResultsInterfaceFactory $searchResultsFactory,
        CategoryVersionCollectionFactory             $categoryVersionCollectionFactory,
        CollectionProcessorInterface                 $collectionProcessor
    )
    {
        $this->versionFactory = $versionFactory;
        $this->versionResource = $versionResource;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->categoryVersionCollectionFactory = $categoryVersionCollectionFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function getNew(): CategoryVersionInterface
    {
        return $this->versionFactory->create();
    }

    /**
     * @inheritDoc
     */
    public function save(CategoryVersionInterface $version): CategoryVersionInterface
    {
        $this->versionResource->save($version);
        return $version;
    }

    /**
     * @inheritDoc
     */
    public function deleteById($id): void
    {
        $version = $this->getById($id);
        unset($this->versions[$id]);
        $this->delete($version);
    }

    /**
     * @inheritDoc
     */
    public function getById(int $id): CategoryVersionInterface
    {
        if (!isset($this->versions[$id])) {
            $version = $this->versionFactory->create();
            $this->versionResource->load($version, $id);
            if (!$version->getId()) {
                throw new NoSuchEntityException(__("No category version with id $id exists."));
            }
            $this->versions[$version->getId()] = $version;
        }
        return $this->versions[$id];
    }

    /**
     * @inheritDoc
     */
    public function delete(CategoryVersionInterface $version): void
    {
        $this->versionResource->delete($version);
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria = null): CategoryVersionSearchResultsInterface
    {
        $collection = $this->categoryVersionCollectionFactory->create();
        $searchResults = $this->searchResultsFactory->create();
        if ($searchCriteria) {
            $this->collectionProcessor->process($searchCriteria, $collection);
            $searchResults->setSearchCriteria($searchCriteria);
        }
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }
}
