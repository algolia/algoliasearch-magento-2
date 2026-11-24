<?php

namespace Algolia\AlgoliaSearch\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Resolves store display names and labels for admin, CLI and log output.
 *
 * Names are memoized per store ID for the lifetime of the instance, so a caller can resolve at
 * the point of use instead of threading names through its call signatures. Because the cache is
 * never invalidated, a store renamed during a run keeps the name of its first lookup.
 */
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
     *                                 fetch error; when false, that store falls back to a plain ID
     *                                 label while the rest keep their resolved names
     *
     * @throws LocalizedException When $failOnMissingStore is true and a store name cannot be fetched
     *
     * @return array<int, string> Store names keyed by store ID
     */
    public function resolveStoreNames(array $storeIds, bool $failOnMissingStore = false): array
    {
        $storeNames = [];
        foreach ($storeIds as $storeId) {
            try {
                $storeNames[$storeId] = $this->getStoreName($storeId);
            } catch (LocalizedException $e) {
                if ($failOnMissingStore) {
                    throw $e;
                }

                $storeNames[$storeId] = $this->getGenericStoreLabel($storeId);
            }
        }

        return $storeNames;
    }

    /**
     * Provides a consistent per-store display label: "Store 2 (English)"
     * Falls back to a plain ID label when the store cannot be resolved
     * (e.g. it was deleted while its jobs were still pending).
     */
    public function getStoreLabel(int $storeId): string
    {
        $label = $this->getGenericStoreLabel($storeId);
        try {
            return $label . ' (' . $this->getStoreName($storeId) . ')';
        } catch (LocalizedException $e) {
            return $label;
        }
    }

    protected function getGenericStoreLabel(int $storeId): string
    {
        return 'Store ' . $storeId;
    }
}
