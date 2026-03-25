<?php

namespace Algolia\AlgoliaSearch\Api;

use Algolia\AlgoliaSearch\Api\Data\QueueArchiveInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface QueueArchiveRepositoryInterface
 *
 * @package Algolia\AlgoliaSearch\Api
 */
interface QueueArchiveRepositoryInterface
{
    /**
     * Save queue archive
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return QueueArchiveInterface
     */
    public function save(QueueArchiveInterface $queueArchive);

    /**
     * Retrieve queue archive by id
     *
     * @param int $id
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return QueueArchiveInterface
     */
    public function getById($id);

    /**
     * Retrieve queue archives matching the specified criteria
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete queue archive
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return bool true on success
     */
    public function delete(QueueArchiveInterface $queueArchive);

    /**
     * Delete queue archive by ID
     *
     * @param int $id
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return bool true on success
     */
    public function deleteById($id);
}
