<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Model\IndexOptions;
use Magento\Framework\Exception\NoSuchEntityException;

class IndexOptionsBuilder
{
    public function __construct(
        protected IndexNameFetcher $indexNameFetcher
    ) {}

    /**
     * Automatically converts information related to an index into a IndexOptions objects
     *
     * @param string|null $indexSuffix
     * @param int|null $storeId
     * @param bool|null $isTmp
     * @return IndexOptions
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    public function buildWithComputedIndex(
        ?string $indexSuffix = null,
        ?int $storeId = null,
        ?bool $isTmp = false
    ): IndexOptionsInterface
    {
        $indexOptions =  new IndexOptions([
            IndexOptionsInterface::STORE_ID => $storeId,
            IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
            IndexOptionsInterface::IS_TMP => $isTmp
        ]);

        return $this->build($indexOptions);
    }

    /**
     * This method only ensures possibility to create IndexOptions with an enforced index name
     *
     * @param string|null $indexName
     * @param int|null $storeId
     * @return IndexOptions
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    public function buildWithEnforcedIndex(?string $indexName = null, ?int $storeId = null): IndexOptionsInterface
    {
        $indexOptions = new IndexOptions([
            IndexOptionsInterface::INDEX_NAME => $indexName,
            IndexOptionsInterface::STORE_ID => $storeId
        ]);

        return $this->build($indexOptions);
    }

    /**
     * Build IndexOptions object by setting its index name
     *
     * @param IndexOptionsInterface $indexOptions
     * @return IndexOptionsInterface
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    protected function build(IndexOptionsInterface $indexOptions): IndexOptionsInterface
    {
        $indexOptions->setIndexName($this->computeIndexName($indexOptions));

        return $indexOptions;
    }

    /**
     * Determines the index name giving it suffix, store id and if it's temporary or not
     *
     * @param IndexOptionsInterface $indexOptions
     * @return string|null
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    protected function computeIndexName(IndexOptionsInterface $indexOptions): ?string
    {
        if (!is_null($indexOptions->getIndexName())) {
            return $indexOptions->getIndexName();
        }

        if (is_null($indexOptions->getIndexSuffix())) {
            throw new AlgoliaException('Index suffix is mandatory in case no enforced index name is specified.');
        }

        return $this->indexNameFetcher->getIndexName(
            $indexOptions->getIndexSuffix(),
            $indexOptions->getStoreId(),
            $indexOptions->isTemporaryIndex()
        );
    }
}
