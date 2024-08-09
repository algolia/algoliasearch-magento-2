<?php

namespace Algolia\AlgoliaSearch\Model\Backend;

use Algolia\AlgoliaSearch\Helper\Configuration\ConfigChecker;
use Algolia\AlgoliaSearch\Registry\ReplicaState;
use Magento\Config\Model\Config\Backend\Serialized\ArraySerialized;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;

class Sorts extends ArraySerialized
{
    public function __construct(
        Context                 $context,
        Registry                $registry,
        ScopeConfigInterface    $config,
        TypeListInterface       $cacheTypeList,
        protected ReplicaState  $replicaState,
        protected ConfigChecker $configChecker,
        AbstractResource        $resource = null,
        AbstractDb              $resourceCollection = null,
        array                   $data = [],
        Json                    $serializer = null
    )
    {
        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $resource,
            $resourceCollection,
            $data,
            $serializer);
    }

    /**
     * @return $this
     * @throws NoSuchEntityException
     */
    public function afterSave(): \Magento\Framework\App\Config\Value
    {
        $this->replicaState->setAppliedScope($this->getScope(), $this->getScopeId());

        $storeIds = $this->configChecker->getAffectedStoreIds(
            $this->getPath(),
            $this->getScope(),
            $this->getScopeId()
        );

        foreach ($storeIds as $storeId) {
            if ($this->isValueChanged()) {
                $this->replicaState->setChangeState(ReplicaState::REPLICA_STATE_CHANGED, $storeId);
                $this->replicaState->setOriginalSortConfiguration($this->serializer->unserialize($this->getOldValue()), $storeId);
                $this->replicaState->setUpdatedSortConfiguration($this->serializer->unserialize($this->getValue()), $storeId);
            } else {
                $this->replicaState->setChangeState(ReplicaState::REPLICA_STATE_UNCHANGED, $storeId);
            }
        }

        return parent::afterSave();
    }
}
