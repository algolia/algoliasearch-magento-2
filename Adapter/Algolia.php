<?php

namespace Algolia\AlgoliaSearch\Adapter;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data as AlgoliaHelper;
use Magento\CatalogSearch\Helper\Data;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Search\Adapter\Mysql\Aggregation\Builder as AggregationBuilder;
use Magento\Framework\Search\Adapter\Mysql\DocumentFactory;
use Magento\Framework\Search\Adapter\Mysql\Mapper;
use Magento\Framework\Search\Adapter\Mysql\ResponseFactory;
use Magento\Framework\Search\Adapter\Mysql\TemporaryStorageFactory;
use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * MySQL Search Adapter
 */
class Algolia implements AdapterInterface
{
    /**
     * Mapper instance
     *
     * @var Mapper
     */
    protected $mapper;

    /**
     * Response Factory
     *
     * @var ResponseFactory
     */
    protected $responseFactory;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resource;

    /**
     * @var AggregationBuilder
     */
    private $aggregationBuilder;

    /**
     * @var TemporaryStorageFactory
     */
    private $temporaryStorageFactory;
    /**
     * @var ConfigHelper
     */
    protected $config;
    /**
     * @var Data
     */
    protected $catalogSearchHelper;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var AlgoliaHelper
     */
    protected $algoliaHelper;

    protected $request;

    protected $documentFactory;

    /**
     * @param Mapper                  $mapper
     * @param ResponseFactory         $responseFactory
     * @param ResourceConnection      $resource
     * @param AggregationBuilder      $aggregationBuilder
     * @param TemporaryStorageFactory $temporaryStorageFactory
     */
    public function __construct(
        Mapper $mapper,
        ResponseFactory $responseFactory,
        ResourceConnection $resource,
        AggregationBuilder $aggregationBuilder,
        TemporaryStorageFactory $temporaryStorageFactory,
        ConfigHelper $config,
        Data $catalogSearchHelper,
        StoreManagerInterface $storeManager,
        AlgoliaHelper $algoliaHelper,
        Http $request,
        DocumentFactory $documentFactory
    ) {
        $this->mapper = $mapper;
        $this->responseFactory = $responseFactory;
        $this->resource = $resource;
        $this->aggregationBuilder = $aggregationBuilder;
        $this->temporaryStorageFactory = $temporaryStorageFactory;
        $this->config = $config;
        $this->catalogSearchHelper = $catalogSearchHelper;
        $this->storeManager = $storeManager;
        $this->algoliaHelper = $algoliaHelper;
        $this->request = $request;
        $this->documentFactory = $documentFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function query(RequestInterface $request)
    {
        $query = $this->catalogSearchHelper->getEscapedQueryText();

        $storeId = $this->storeManager->getStore()->getId();
        $temporaryStorage = $this->temporaryStorageFactory->create();

        $documents = [];
        $table = null;

        if ($this->isAllowed($storeId)
            && ($this->isSearch($storeId) || $this->isReplaceCategory($storeId) || $this->isReplaceAdvancedSearch($storeId))
        ) {
            $algolia_query = $query !== '__empty__' ? $query : '';

            //If instant search is on, do not make a search query unless SEO request is set to 'Yes'
            if (!$this->config->isInstantEnabled($storeId) || $this->config->makeSeoRequest($storeId)) {
                $documents = $this->algoliaHelper->getSearchResult($algolia_query, $storeId);
            }

            $getDocumentMethod = 'getDocument21';
            $storeDocumentsMethod = 'storeApiDocuments';
            if (version_compare($this->config->getMagentoVersion(), '2.1.0', '<') === true) {
                $getDocumentMethod = 'getDocument20';
                $storeDocumentsMethod = 'storeDocuments';
            }

            $apiDocuments = array_map(function ($document) use ($getDocumentMethod) {
                return $this->{$getDocumentMethod}($document);
            }, $documents);

            $table = $temporaryStorage->{$storeDocumentsMethod}($apiDocuments);
        } else {
            $query = $this->mapper->buildQuery($request);
            $table = $temporaryStorage->storeDocumentsFromSelect($query);
            $documents = $this->getDocuments($table);
        }

        $aggregations = $this->aggregationBuilder->build($request, $table);

        $response = [
            'documents'    => $documents,
            'aggregations' => $aggregations,
        ];

        return $this->responseFactory->create($response);
    }

    /**
     * Executes query and return raw response
     *
     * @param Table $table
     *
     * @return array
     *
     * @throws \Zend_Db_Exception
     */
    private function getDocuments(Table $table)
    {
        $connection = $this->getConnection();
        $select = $connection->select();
        $select->from($table->getName(), ['entity_id', 'score']);

        return $connection->fetchAssoc($select);
    }

    /**
     * @return false|\Magento\Framework\DB\Adapter\AdapterInterface
     */
    private function getConnection()
    {
        return $this->resource->getConnection();
    }

    private function getDocument20($document)
    {
        return new \Magento\Framework\Search\Document($document['entity_id'], ['score' => new \Magento\Framework\Search\DocumentField('score', $document['score'])]);
    }

    private function getDocument21($document)
    {
        return $this->documentFactory->create($document);
    }

    /**
     * Check if algolia is properly configured and
     * enabled
     *
     * @param  int     $storeId
     * @return boolean
     */
    private function isAllowed($storeId)
    {
        return (
            $this->config->getApplicationID($storeId)
            && $this->config->getAPIKey($storeId)
            && $this->config->isEnabledFrontEnd($storeId)
        );
    }

    /**
     * @param  int     $storeId
     * @return boolean
     */
    private function isSearch($storeId)
    {
        return ($this->request->getFullActionName() == 'catalogsearch_result_index');
    }

    /**
     * Check if Seo Request is enabled
     *
     * @param  int     $storeId
     * @return boolean
     */
    private function isSeoRequestEnabled($storeId)
    {
        return ($this->config->makeSeoRequest($storeId) === '1');
    }

    /**
     * Check if should replace category results
     *
     * @param  int     $storeId
     * @return boolean
     */
    private function isReplaceCategory($storeId)
    {
        return (
            $this->request->getControllerName() == 'category'
            && (
                $this->config->replaceCategories($storeId) == true
                || $this->config->isInstantEnabled($storeId) == true
            )
        );
    }

    /**
     * Check if replace advancend search result
     *
     * @param  int      $storeId
     * @return boolean
     */
    private function isReplaceAdvancedSearch($storeId)
    {
        return (
            $this->request->getFullActionName() == 'catalogsearch_advanced_result'
            && (
                $this->config->replaceCategories($storeId) == true
                || $this->config->isInstantEnabled($storeId) == true
            )
        );
    }
}
