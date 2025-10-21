<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\Builder\EntityIndexOptionsBuilderInterface;
use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Magento\Framework\Exception\NoSuchEntityException;

abstract class AbstractEntityIndexOptionsBuilder extends IndexOptionsBuilder implements EntityIndexOptionsBuilderInterface
{
    /**
     * Safely builds index options with AlgoliaException handling
     * Template method that handles the try/catch logic for all entity implementations
     *
     * @throws NoSuchEntityException
     */
    protected function safeBuildWithComputedIndex(string $indexSuffix, int $storeId, bool $isTmp = false): IndexOptionsInterface
    {
        try {
            return $this->buildWithComputedIndex($indexSuffix, $storeId, $isTmp);
        } catch (AlgoliaException $e) {
            // This should not happen with a valid suffix, but log it for debugging
            $this->logger->error("Unexpected AlgoliaException in buildEntityIndexOptions.", [
                'suffix' => $indexSuffix,
                'storeId' => $storeId,
                'isTmp' => $isTmp,
                'exception' => $e->getMessage()
            ]);

            // Return default index options to allow other processes to handle the error condition
            return $this->indexOptionsInterfaceFactory->create([
                'data' => [
                    IndexOptionsInterface::STORE_ID => $storeId,
                    IndexOptionsInterface::INDEX_SUFFIX => $indexSuffix,
                    IndexOptionsInterface::IS_TMP => $isTmp
                ]
            ]);
        }
    }
}
