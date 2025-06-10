<?php

namespace Algolia\AlgoliaSearch\Model\Cache;

use Algolia\AlgoliaSearch\Model\Cache\Type\Indexer;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;

class IndexCollectionSize
{
    const NOT_FOUND = -1;

    public function __construct(
        protected CacheInterface $cache,
        protected StateInterface $state,
        protected TypeListInterface $typeList
    ) {}

    public function get(int $storeId): int
    {
        if (!$this->isCacheAvailable()) {
            return self::NOT_FOUND;
        }

        /** @var string|false $data */
        $data = $this->cache->load($this->getCacheKey($storeId));
        if ($data === false) {
            return self::NOT_FOUND;
        }

        return (int) $data;
    }

    public function set(int $storeId, int $value, ?int $ttl = null): void
    {
        if ($this->isCacheAvailable()) {
            $this->cache->save($value, $this->getCacheKey($storeId), [Indexer::CACHE_TAG], $ttl);
        }
    }

    protected function remove(int $storeId): void
    {
        $this->cache->remove($this->getCacheKey($storeId));
    }

    public function isCacheAvailable(): bool
    {
        return $this->state->isEnabled(Indexer::TYPE_IDENTIFIER)
            && !array_key_exists(Indexer::TYPE_IDENTIFIER, $this->typeList->getInvalidated());
    }

    protected function getCacheKey(int $storeId): string
    {
        return sprintf('%s_%d', Indexer::TYPE_IDENTIFIER, $storeId);
    }

    public function clear(?int $storeId = null): void
    {
        if (is_null($storeId)) {
            $this->typeList->invalidate(Indexer::TYPE_IDENTIFIER);
            $this->typeList->cleanType(Indexer::TYPE_IDENTIFIER);
        }
        else {
            $this->remove($storeId);
        }
    }
}
