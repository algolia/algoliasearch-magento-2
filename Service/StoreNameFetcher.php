<?php

namespace Algolia\AlgoliaSearch\Service;

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
     * @return string[]
     * @throws NoSuchEntityException
     */
    public function getStoreNames(array $storeIds): array
    {
        return array_map(
            function($storeId) {
                return $this->getStoreName($storeId);
            },
            $storeIds
        );
    }
}
