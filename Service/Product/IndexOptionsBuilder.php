<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Builder\EntityIndexOptionsBuilderInterface;
use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder as BaseIndexOptionsBuilder;
use Magento\Framework\Exception\NoSuchEntityException;

class IndexOptionsBuilder extends BaseIndexOptionsBuilder implements EntityIndexOptionsBuilderInterface
{
    /**
     * @throws NoSuchEntityException
     */
    public function buildEntityIndexOptions(int $storeId, ?bool $isTmp = false): IndexOptionsInterface
    {
        try {
            return $this->buildWithComputedIndex(ProductHelper::INDEX_NAME_SUFFIX, $storeId, $isTmp);
        } catch (AlgoliaException $e) {
            // This should not happen with a valid suffix, but log it for debugging
            $this->logger->error('Unexpected AlgoliaException in Product\IndexOptionsBuilder::buildEntityIndexOptions', [
                'exception' => $e->getMessage()
            ]);

            // Return default index options to allow other processes to handle the error condition
            return $this->indexOptionsInterfaceFactory->create([
                'data' => [
                    IndexOptionsInterface::STORE_ID => $storeId,
                    IndexOptionsInterface::INDEX_SUFFIX => ProductHelper::INDEX_NAME_SUFFIX,
                    IndexOptionsInterface::IS_TMP => $isTmp
                ]
            ]);
        }
    }
}
