<?php

namespace Algolia\AlgoliaSearch\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class StoreNameFetcher
{
    /** @var array<int, string> */
    protected array $_storeNames = [];

    public function __construct(
        protected StoreManagerInterface $storeManager
    )
    {}

    /**
     * @throws NoSuchEntityException
     */
    public function getStoreName(int $storeId): string
    {
        if (!isset($this->_storeNames[$storeId])) {
            $this->_storeNames[$storeId] = $this->storeManager->getStore($storeId)->getName();
        }

        return $this->_storeNames[$storeId];
    }

    /**
     * @param int[] $storeIds
     *
     * @throws NoSuchEntityException
     *
     * @return string[]
     */
    public function getStoreNames(array $storeIds): array
    {
        return array_map(
            fn($storeId) => $this->getStoreName($storeId),
            $storeIds
        );
    }

    /**
     * Fetches store names keyed by store ID.
     *
     * @param int[] $storeIds
     * @param bool $failOnMissingStore When true, a store that cannot be resolved aborts with the
     *                                 fetch error; when false, it falls back to a plain ID label
     *
     * @throws LocalizedException When $failOnMissingStore is true and a store name cannot be fetched
     *
     * @return array<int, string> Store names keyed by store ID
     */
    public function resolveStoreNames(array $storeIds, bool $failOnMissingStore = false): array
    {
        try {
            return array_combine($storeIds, $this->getStoreNames($storeIds));
        } catch (LocalizedException $e) {
            if ($failOnMissingStore) {
                throw $e;
            }

            return array_combine(
                $storeIds,
                array_map(static fn (int $storeId): string => 'Store ID ' . $storeId, $storeIds)
            );
        }
    }
}
