<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml\IndexingManager;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

class Section implements ArgumentInterface
{
    protected string $entity;

    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected ConfigHelper $configHelper,
        protected StoreNameFetcher $storeNameFetcher,
        protected IndexNameFetcher $indexNameFetcher
    ) {}

    /**
     * @param string $entity
     * @return void
     */
    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    /**
     * @return string
     */
    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * @return string
     */
    public function getSectionTitle(): string
    {
        return ucfirst(str_replace('_', ' ', $this->getEntity()));
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getRelatedIndices(): array
    {
        $relatedIndices = [];

        foreach ($this->storeManager->getStores() as $store) {
            $relatedIndices[$store->getId()] = [
                'name' => $this->storeNameFetcher->getStoreName($store->getId()),
                'index' => $this->indexNameFetcher->getIndexName(
                    '_' . $this->getEntity(),
                    $store->getId()
                )
            ];
        }

        return $relatedIndices;
    }

    /**
     * @param string $indexName
     * @param int $storeId
     * @return string
     */
    public function getIndexUrl(string $indexName, int $storeId): string
    {
        $url = 'https://dashboard.algolia.com/apps/';
        $url .= $this->configHelper->getApplicationID($storeId);
        $url .= '/explorer/browse/' . $indexName;

        return $url;
    }
}
