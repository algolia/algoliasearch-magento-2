<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class IndexNameFetcher
{
    public function __construct(
        protected ConfigHelper          $configHelper,
        protected StoreManagerInterface $storeManager
    )
    {}

    /** @var string */
    public const INDEX_TEMP_SUFFIX = '_tmp';

    /**
     * @param string $indexSuffix
     * @param int|null $storeId
     * @param bool $tmp
     * @return string
     * @throws NoSuchEntityException
     */
    public function getIndexName(string $indexSuffix, ?int $storeId = null, bool $tmp = false): string
    {
        return $this->getBaseIndexName($storeId) . $indexSuffix . ($tmp ? self::INDEX_TEMP_SUFFIX : '');
    }

    /**
     * @param int|null $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    public function getBaseIndexName(?int $storeId = null): string
    {
        return $this->configHelper->getIndexPrefix($storeId) . $this->storeManager->getStore($storeId)->getCode();
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getProductIndexName(int $storeId, bool $tmp = false): string
    {
        return $this->getIndexName(ProductHelper::INDEX_NAME_SUFFIX, $storeId, $tmp);
    }

}