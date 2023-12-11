<?php

namespace Algolia\AlgoliaSearch\Model\Backend;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

class Replica extends Value
{
    /** @var StoreManagerInterface */
    protected $storeManager;
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var ProductHelper
     */
    protected $productHelper;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param StoreManagerInterface $storeManager
     * @param Data $helper
     * @param ProductHelper $productHelper
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context               $context,
        Registry              $registry,
        ScopeConfigInterface  $config,
        TypeListInterface     $cacheTypeList,
        StoreManagerInterface $storeManager,
        Data                  $helper,
        ProductHelper         $productHelper,
        AbstractResource      $resource = null,
        AbstractDb            $resourceCollection = null,
        array                 $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->productHelper = $productHelper;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return $this
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    public function afterSave()
    {
        if ($this->isValueChanged()) {
            try {
                $storeIds = array_keys($this->storeManager->getStores());
                foreach ($storeIds as $storeId) {
                    $indexName = $this->helper->getIndexName($this->productHelper->getIndexNameSuffix(), $storeId);
                    $this->productHelper->handlingReplica($indexName, $storeId);
                }
            } catch (AlgoliaException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
            }
        }
        return parent::afterSave();
    }
}

