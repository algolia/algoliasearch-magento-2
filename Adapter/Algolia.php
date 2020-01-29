<?php

namespace Algolia\AlgoliaSearch\Adapter;

use Algolia\AlgoliaSearch\Adapter\Aggregation\Builder as AlgoliaAggregationBuilder;
use Algolia\AlgoliaSearch\Exceptions\UnreachableException;
use Algolia\AlgoliaSearch\Helper\AdapterHelper;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Select;
use Magento\Framework\Search\Adapter\Mysql\Aggregation\Builder as AggregationBuilder;
use Magento\Framework\Search\Adapter\Mysql\DocumentFactory;
use Magento\Framework\Search\Adapter\Mysql\Mapper;
use Magento\Framework\Search\Adapter\Mysql\ResponseFactory;
use Magento\Framework\Search\Adapter\Mysql\TemporaryStorageFactory;
use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\RequestInterface;

/**
 * Algolia Search Adapter
 */
class Algolia implements AdapterInterface
{
    /** @var Mapper */
    private $mapper;

    /** @var ResponseFactory */
    private $responseFactory;

    /** @var ResourceConnection */
    private $resource;

    /** @var AggregationBuilder */
    private $aggregationBuilder;

    /** @var TemporaryStorageFactory */
    private $temporaryStorageFactory;

    /** @var AdapterHelper */
    private $adapterHelper;

    /** @var AlgoliaAggregationBuilder */
    private $algoliaAggregationBuilder;

    /** @var DocumentFactory */
    private $documentFactory;

    private $countSqlSkipParts = [
        Select::LIMIT_COUNT => true,
        Select::LIMIT_OFFSET => true,
    ];

    /**
     * @param Mapper $mapper
     * @param ResponseFactory $responseFactory
     * @param ResourceConnection $resource
     * @param AggregationBuilder $aggregationBuilder
     * @param TemporaryStorageFactory $temporaryStorageFactory
     * @param AdapterHelper $adapterHelper
     * @param AlgoliaAggregationBuilder $algoliaAggregationBuilder
     * @param DocumentFactory $documentFactory
     */
    public function __construct(
        Mapper $mapper,
        ResponseFactory $responseFactory,
        ResourceConnection $resource,
        AggregationBuilder $aggregationBuilder,
        TemporaryStorageFactory $temporaryStorageFactory,
        AdapterHelper $adapterHelper,
        AlgoliaAggregationBuilder $algoliaAggregationBuilder,
        DocumentFactory $documentFactory
    ) {
        $this->mapper = $mapper;
        $this->responseFactory = $responseFactory;
        $this->resource = $resource;
        $this->aggregationBuilder = $aggregationBuilder;
        $this->temporaryStorageFactory = $temporaryStorageFactory;
        $this->adapterHelper = $adapterHelper;
        $this->algoliaAggregationBuilder = $algoliaAggregationBuilder;
        $this->documentFactory = $documentFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function query(RequestInterface $request)
    {
        if (!$this->adapterHelper->isAllowed()
            || !(
                $this->adapterHelper->isSearch() ||
                $this->adapterHelper->isReplaceCategory() ||
                $this->adapterHelper->isReplaceAdvancedSearch() ||
                $this->adapterHelper->isLandingPage()
            )
        ) {
            return $this->nativeQuery($request);
        }

        $temporaryStorage = $this->temporaryStorageFactory->create();
        $documents = [];
        $totalHits = 0;
        $table = null;
        $facetsFromAlgolia = null;

        try {
            // If instant search is on, do not make a search query unless SEO request is set to 'Yes'
            if (!$this->adapterHelper->isInstantEnabled() || $this->adapterHelper->makeSeoRequest()) {
                list($documents, $totalHits, $facetsFromAlgolia) = $this->adapterHelper->getDocumentsFromAlgolia();
            }

            $apiDocuments = array_map([$this, 'getApiDocument'], $documents);
            $table = $temporaryStorage->storeApiDocuments($apiDocuments);
        } catch (UnreachableException $e) {
            return $this->nativeQuery($request);
        }

        $aggregations = $this->algoliaAggregationBuilder->build($request, $table, $documents, $facetsFromAlgolia);

        $response = [
            'documents' => $documents,
            'aggregations' => $aggregations,
            'total' => $totalHits,
        ];

        return $this->responseFactory->create($response);
    }

    private function nativeQuery(RequestInterface $request)
    {
        $query = $this->mapper->buildQuery($request);
        $temporaryStorage = $this->temporaryStorageFactory->create();
        $table = $temporaryStorage->storeDocumentsFromSelect($query);

        $documents = $this->getDocuments($table);

        $aggregations = $this->aggregationBuilder->build($request, $table, $documents);
        $response = [
            'documents' => $documents,
            'aggregations' => $aggregations,
            'total' => $this->getSize($query),
        ];

        return $this->responseFactory->create($response);
    }

    private function getApiDocument($document)
    {
        return $this->documentFactory->create($document);
    }

    /**
     * Executes query and return raw response
     *
     * @param Table $table
     *
     * @throws \Zend_Db_Exception
     *
     * @return array
     */
    private function getDocuments(Table $table)
    {
        $connection = $this->getConnection();
        $select = $connection->select();
        $select->from($table->getName(), ['entity_id', 'score']);

        return $connection->fetchAssoc($select);
    }

    /** @return \Magento\Framework\DB\Adapter\AdapterInterface */
    private function getConnection()
    {
        return $this->resource->getConnection();
    }

    /**
     * Get rows size
     *
     * @param Select $query
     *
     * @return int
     */
    private function getSize(Select $query)
    {
        $sql = $this->getSelectCountSql($query);
        $parentSelect = $this->getConnection()->select();
        $parentSelect->from(['core_select' => $sql]);
        $parentSelect->reset(Select::COLUMNS);
        $parentSelect->columns('COUNT(*)');
        $totalRecords = $this->getConnection()->fetchOne($parentSelect);

        return (int) $totalRecords;
    }

    /**
     * Reset limit and offset
     *
     * @param Select $query
     *
     * @return Select
     */
    private function getSelectCountSql(Select $query)
    {
        foreach ($this->countSqlSkipParts as $part => $toSkip) {
            if ($toSkip) {
                $query->reset($part);
            }
        }

        return $query;
    }
}
