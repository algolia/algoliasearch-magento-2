<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Builder\EntityIndexOptionsBuilderInterface;
use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterfaceFactory;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder as BaseIndexOptionsBuilder;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class IndexOptionsBuilder extends BaseIndexOptionsBuilder implements EntityIndexOptionsBuilderInterface
{
    public function __construct(
        protected SortingTransformer $sortingTransformer,
        protected HttpContext        $httpContext,
        IndexNameFetcher             $indexNameFetcher,
        IndexOptionsInterfaceFactory $indexOptionsInterfaceFactory,
        DiagnosticsLogger            $logger,
    ) {
        parent::__construct($indexNameFetcher, $indexOptionsInterfaceFactory, $logger);
    }

    /**
     * @throws NoSuchEntityException
     */
    public function buildEntityIndexOptions(int $storeId, ?bool $isTmp = false): IndexOptionsInterface
    {
        return $this->buildWithComputedIndex(ProductHelper::INDEX_NAME_SUFFIX, $storeId, $isTmp);
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function buildReplicaIndexOptions(
        int $storeId,
        string $sortField,
        string $sortDirection) : IndexOptionsInterface
    {
        $replicaIndexName = $this->getReplicaIndexName($storeId, $sortField, $sortDirection);
        return $replicaIndexName
            ? $this->buildWithEnforcedIndex($replicaIndexName, $storeId)
            : $this->buildEntityIndexOptions($storeId);
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getReplicaIndexName(int $storeId, string $sortField, string $sortDirection): ?string
    {
        $availableSorts = $this->sortingTransformer->getSortingIndices($storeId, $this->getCustomerGroupId());
        $sort = null;
        foreach ($availableSorts as $item) {
            if (strcasecmp($item[ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME], $sortField) === 0
                && strcasecmp($item[ReplicaManagerInterface::SORT_KEY_DIRECTION], $sortDirection) === 0) {
                $sort = $item;
                break;
            }
        }
        return $sort[ReplicaManagerInterface::SORT_KEY_INDEX_NAME] ?? null;
    }

    protected function getCustomerGroupId(): ?int
    {
        return $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);
    }
}
