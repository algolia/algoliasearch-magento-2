<?php

namespace Algolia\AlgoliaSearch\DataProvider\Analytics;

use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollection;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollection;

class IndexEntityDataProvider
{
    /** @var Data */
    private $dataHelper;

    /** @var ProductHelper */
    private $productHelper;

    /** @var CategoryHelper */
    private $categoryHelper;

    /** @var PageHelper */
    private $pageHelper;

    /** @var ProductCollection */
    private $productCollection;

    /** @var CategoryCollection */
    private $categoryCollection;

    /** @var PageCollection */
    private $pageCollection;

    /** @var array */
    private $entityIndexes = [];

    public function __construct(
        Data $dataHelper,
        ProductHelper $productHelper,
        CategoryHelper $categoryHelper,
        PageHelper $pageHelper,
        ProductCollection $productCollection,
        CategoryCollection $categoryCollection,
        PageCollection $pageCollection
    ) {
        $this->dataHelper = $dataHelper;
        $this->productHelper = $productHelper;
        $this->categoryHelper = $categoryHelper;
        $this->pageHelper = $pageHelper;

        $this->productCollection = $productCollection;
        $this->categoryCollection = $categoryCollection;
        $this->pageCollection = $pageCollection;
    }

    /**
     * @param $storeId
     *
     * @return array
     */
    public function getEntityIndexes($storeId)
    {
        if (empty($this->entityIndexes)) {
            $this->entityIndexes = [
                'products' => $this->dataHelper->getIndexName($this->productHelper->getIndexNameSuffix(), $storeId),
                'categories' => $this->dataHelper->getIndexName($this->categoryHelper->getIndexNameSuffix(), $storeId),
                'pages' => $this->dataHelper->getIndexName($this->pageHelper->getIndexNameSuffix(), $storeId),
            ];
        }

        return $this->entityIndexes;
    }

    /**
     * @param $entity
     * @param $storeId
     *
     * @return mixed
     */
    public function getIndexNameByEntity($entity, $storeId)
    {
        $indexes = $this->getEntityIndexes($storeId);

        return $indexes[$entity];
    }

    /**
     * @param int $storeId
     * @param array $objectIds
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getProductCollection($storeId, $objectIds)
    {
        $collection = $this->productCollection->create();

        $collection
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('entity_id', ['in' => $objectIds]);

        return $collection;
    }

    /**
     * @param int $storeId
     * @param array $objectIds
     *
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    public function getCategoryCollection($storeId, $objectIds)
    {
        $collection = $this->categoryCollection->create();

        $collection
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('entity_id', ['in' => $objectIds]);

        return $collection;
    }

    /**
     * @param int $storeId
     * @param array $objectIds
     *
     * @return \Magento\Cms\Model\ResourceModel\Page\Collection
     */
    public function getPageCollection($storeId, $objectIds)
    {
        $collection = $this->pageCollection->create();

        $collection
            ->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addFieldToSelect(['page_id', 'title', 'identifier'])
            ->addFieldToFilter('page_id', ['in' => $objectIds]);

        return $collection;
    }
}
