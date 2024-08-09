<?php

namespace Algolia\AlgoliaSearch\Model\Backend;

use Algolia\AlgoliaSearch\Helper\Configuration\ConfigChecker;
use Algolia\AlgoliaSearch\Registry\ReplicaState;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class EnableCustomerGroups extends Value
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
        array                   $data = []
    )
    {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return $this
     * @throws NoSuchEntityException
     */
    public function afterSave(): Value
    {
        $this->replicaState->setAppliedScope($this->getScope(), $this->getScopeId());
        $this->replicaState->setCustomerGroupsEnabled((bool) $this->getValue());

        $storeIds = $this->configChecker->getAffectedStoreIds(
            $this->getPath(),
            $this->getScope(),
            $this->getScopeId()
        );

        foreach ($storeIds as $storeId) {
            $this->replicaState->setChangeState(
                $this->isValueChanged()
                    ? ReplicaState::REPLICA_STATE_CHANGED
                    : ReplicaState::REPLICA_STATE_UNCHANGED,
                $storeId);
        }

        return parent::afterSave();
    }
}

