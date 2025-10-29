<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterfaceFactory;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Magento\Framework\Exception\NoSuchEntityException;

class IndexOptionsBuilder
{
    public function __construct(
        protected IndexNameFetcher             $indexNameFetcher,
        protected IndexOptionsInterfaceFactory $indexOptionsInterfaceFactory,
        protected DiagnosticsLogger            $logger
    ) {}

    /**
     * Automatically converts information related to an index into a IndexOptions objects
     *
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    public function buildWithComputedIndex(
        ?string $indexSuffix = null,
        ?int    $storeId = null,
        ?bool   $isTmp = false
    ): IndexOptionsInterface
    {
        $indexOptions =  $this->indexOptionsInterfaceFactory->create([
            'data' => [
                IndexOptionsInterface::STORE_ID     => $storeId,
                IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
                IndexOptionsInterface::IS_TMP       => $isTmp
            ]
        ]);

        $indexOptions->setIndexName($this->computeIndexName($indexOptions));

        return $indexOptions;
    }

    /**
     * This method only ensures possibility to create IndexOptions with an enforced index name
     */
    public function buildWithEnforcedIndex(?string $indexName = null, ?int $storeId = null): IndexOptionsInterface
    {
        return $this->indexOptionsInterfaceFactory->create([
            'data' => [
                IndexOptionsInterface::INDEX_NAME => $indexName,
                IndexOptionsInterface::STORE_ID   => $storeId
            ]
        ]);
    }

    /**
     * Determines the index name giving it suffix, store id and if it's temporary or not
     *
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    protected function computeIndexName(IndexOptionsInterface $indexOptions): string
    {
        if ($indexOptions->getIndexName() !== null) {
            return $indexOptions->getIndexName(); // respect enforced index
        }

        if ($indexOptions->getIndexSuffix() === null || $indexOptions->getIndexSuffix() === '') {
            $msg = "Index name could not be computed due to missing suffix.";
            $this->logger->error(
                $msg,
                [
                    'storeId' => $indexOptions->getStoreId(),
                    'isTmp' => $indexOptions->isTemporaryIndex()
                ]
            );
            throw new AlgoliaException($msg);
        }

        return $this->indexNameFetcher->getIndexName(
            $indexOptions->getIndexSuffix(),
            $indexOptions->getStoreId(),
            $indexOptions->isTemporaryIndex()
        );
    }
}
