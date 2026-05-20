<?php

namespace Algolia\AlgoliaSearch\Model\Backend;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Exception;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class SuggestionsIndexName extends Value
{
    public function __construct(
        Context                    $context,
        Registry                   $registry,
        ScopeConfigInterface       $config,
        TypeListInterface          $cacheTypeList,
        protected AlgoliaConnector $algoliaConnector,
        protected RequestInterface $request,
        ?AbstractResource          $resource = null,
        ?AbstractDb                $resourceCollection = null,
        array                      $data = []
    ){
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @throws \Throwable
     * @throws AlgoliaException
     */
    public function beforeSave()
    {
        $value = trim((string) $this->getData('value'));
        $storeId = $this->request->getParam('store');

        try {
            if (!$this->algoliaConnector->indexExists($value, $storeId)) {
                throw new AlgoliaException("Index '{$value}' does not exist.");
            }
        } catch (Exception $e) {
            throw new AlgoliaException($e->getMessage());
        }

        return parent::beforeSave();
    }
}
