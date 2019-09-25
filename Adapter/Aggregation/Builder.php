<?php

namespace Algolia\AlgoliaSearch\Adapter\Aggregation;

use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Search\Adapter\Aggregation\AggregationResolverInterface;
use Magento\Framework\Search\Adapter\Mysql\Aggregation\Builder\Container as AggregationContainer;
use Magento\Framework\Search\Adapter\Mysql\Aggregation\DataProviderContainer;
use Magento\Framework\Search\Adapter\Mysql\TemporaryStorage;
use Magento\Framework\Search\RequestInterface;

class Builder
{
    /** @var DataProviderContainer */
    private $dataProviderContainer;

    /** @var AggregationContainer */
    private $aggregationContainer;

    /** @var ResourceConnection */
    private $resource;

    /** @var AggregationResolverInterface */
    private $aggregationResolver;

    /** @var ProductFactory */
    private $productFactory;

    /**
     * @param ResourceConnection $resource
     * @param DataProviderContainer $dataProviderContainer
     * @param AggregationContainer $aggregationContainer
     * @param AggregationResolverInterface $aggregationResolver
     * @param ProductFactory $productFactory
     */
    public function __construct(
        ResourceConnection $resource,
        DataProviderContainer $dataProviderContainer,
        AggregationContainer $aggregationContainer,
        AggregationResolverInterface $aggregationResolver,
        ProductFactory $productFactory
    ) {
        $this->dataProviderContainer = $dataProviderContainer;
        $this->aggregationContainer = $aggregationContainer;
        $this->resource = $resource;
        $this->aggregationResolver = $aggregationResolver;
        $this->productFactory = $productFactory;
    }

    public function build(RequestInterface $request, Table $documentsTable, array $documents, array $facets)
    {
        return $this->processAggregations($request, $documentsTable, $documents, $facets);
    }

    private function processAggregations(RequestInterface $request, Table $documentsTable, $documents, $facets)
    {
        $aggregations = [];
        $documentIds = $documents ? $this->extractDocumentIds($documents) : $this->getDocumentIds($documentsTable);
        $buckets = $this->aggregationResolver->resolve($request, $documentIds);
        $dataProvider = $this->dataProviderContainer->get($request->getIndex());

        foreach ($buckets as $bucket) {
            if (isset($facets[$bucket->getField()])) {
                $aggregations[$bucket->getName()] =
                    $this->formatAggregation($bucket->getField(), $facets[$bucket->getField()]);
            } else {
                $aggregationBuilder = $this->aggregationContainer->get($bucket->getType());
                $aggregations[$bucket->getName()] = $aggregationBuilder->build(
                    $dataProvider,
                    $request->getDimensions(),
                    $bucket,
                    $documentsTable
                );
            }
        }

        return $aggregations;
    }

    private function formatAggregation($attribute, $facetData)
    {
        $aggregation = [];

        foreach ($facetData as $value => $count) {
            $optionId = $this->getOptionIdByLabel($attribute, $value);
            $aggregation[$optionId] = [
                'value' => (string) $optionId,
                'count' => (string) $count,
            ];
        }

        return $aggregation;
    }

    private function getOptionIdByLabel($attributeCode, $optionLabel)
    {
        $product = $this->productFactory->create();
        $isAttributeExist = $product->getResource()->getAttribute($attributeCode);
        $optionId = '';
        if ($isAttributeExist && $isAttributeExist->usesSource()) {
            $optionId = $isAttributeExist->getSource()->getOptionId($optionLabel);
        }

        return $optionId;
    }

    private function extractDocumentIds(array $documents)
    {
        return $documents ? array_keys($documents) : [];
    }

    private function getDocumentIds(Table $documentsTable)
    {
        $select = $this->getConnection()
            ->select()
            ->from($documentsTable->getName(), TemporaryStorage::FIELD_ENTITY_ID);

        return $this->getConnection()->fetchCol($select);
    }

    private function getConnection()
    {
        return $this->resource->getConnection();
    }
}
