<?php

namespace Algolia\AlgoliaSearch\Adapter;

use Algolia\AlgoliaSearch\Helper\AdapterHelper;
use AlgoliaSearch\AlgoliaConnectionException;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
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

    /** @var DocumentFactory */
    private $documentFactory;

    /**
     * @param Mapper $mapper
     * @param ResponseFactory $responseFactory
     * @param ResourceConnection $resource
     * @param AggregationBuilder $aggregationBuilder
     * @param TemporaryStorageFactory $temporaryStorageFactory
     * @param AdapterHelper $adapterHelper
     * @param DocumentFactory $documentFactory
     */
    public function __construct(
        Mapper $mapper,
        ResponseFactory $responseFactory,
        ResourceConnection $resource,
        AggregationBuilder $aggregationBuilder,
        TemporaryStorageFactory $temporaryStorageFactory,
        AdapterHelper $adapterHelper,
        DocumentFactory $documentFactory
    ) {
        $this->mapper = $mapper;
        $this->responseFactory = $responseFactory;
        $this->resource = $resource;
        $this->aggregationBuilder = $aggregationBuilder;
        $this->temporaryStorageFactory = $temporaryStorageFactory;
        $this->adapterHelper = $adapterHelper;
        $this->documentFactory = $documentFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function query(RequestInterface $request)
    {
        if (!$this->adapterHelper->isAllowed()
            || !($this->adapterHelper->isSearch() ||
                $this->adapterHelper->isReplaceCategory() ||
                $this->adapterHelper->isReplaceAdvancedSearch())
        ) {
            return $this->nativeQuery($request);
        }

        $temporaryStorage = $this->temporaryStorageFactory->create();
        $documents = [];
        $table = null;

        try {
            // If instant search is on, do not make a search query unless SEO request is set to 'Yes'
            if (!$this->adapterHelper->isInstantEnabled() || $this->adapterHelper->makeSeoRequest()) {
                $documents = $this->adapterHelper->getDocumentsFromAlgolia();
            }

            $apiDocuments = array_map([$this, 'getApiDocument'], $documents);
            $table = $temporaryStorage->storeApiDocuments($apiDocuments);
        } catch (AlgoliaConnectionException $e) {
            return $this->nativeQuery($request);
        }

        $aggregations = $this->aggregationBuilder->build($request, $table, $documents);
        $response = [
            'documents' => $documents,
            'aggregations' => $aggregations,
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
}
