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
    public function createIndexOptions(?string $indexSuffix = null, ?int $storeId = null, ?bool $isTmp = false): IndexOptions
    {
        $indexOptions =  new IndexOptions([
            IndexOptionsInterface::STORE_ID => $storeId,
            IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
            IndexOptionsInterface::IS_TMP => $isTmp
        ]);

        $indexOptions->setIndexName($this->computeIndexName($indexOptions));

        return $indexOptions;
    }

    /**
     * This method only ensures possibility to create IndexOptions with an enforced index name
     *
     * @param string|null $indexName
     * @param int|null $storeId
     * @return IndexOptions
     */
    public function convertToIndexOptions(?string $indexName = null, ?int $storeId = null): IndexOptions
    {
        return new IndexOptions([
            IndexOptionsInterface::INDEX_NAME => $indexName,
            IndexOptionsInterface::STORE_ID => $storeId
        ]);
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
