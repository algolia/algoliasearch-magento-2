<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel\Job\Grid;

use Algolia\AlgoliaSearch\Api\Data\JobInterface;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job\Collection as JobCollection;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use Psr\Log\LoggerInterface;

class Collection extends JobCollection implements SearchResultInterface
{
    /** @var AggregationInterface */
    protected $aggregations;

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param string|null $mainTable
     * @param string $eventPrefix
     * @param string $eventObject
     * @param string $resourceModel
     * @param string $model
     * @param AdapterInterface|null $connection
     * @param AbstractDb|null $resource
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface        $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface       $eventManager,
        ?string                $mainTable,
        string                 $eventPrefix,
        string                 $eventObject,
        string                 $resourceModel,
        string                 $model = Document::class,
        ?AdapterInterface      $connection = null,
        ?AbstractDb            $resource = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $connection,
            $resource
        );
        $this->_eventPrefix = $eventPrefix;
        $this->_eventObject = $eventObject;
        $this->_init($model, $resourceModel);
        $this->setMainTable($mainTable);

        $this->addStatusToCollection();
    }

    private function addStatusToCollection(): void
    {
        $this->addExpressionFieldToSelect('status', "IF({{retries}} >= {{max_retries}}, '{{error}}', IF({{pid}} IS NULL, '{{new}}', '{{progress}}'))", [
            'pid' => JobInterface::FIELD_PID,
            'retries' => JobInterface::FIELD_RETRIES,
            'max_retries' => JobInterface::FIELD_MAX_RETRIES,
            'new' => JobInterface::STATUS_NEW,
            'error' => JobInterface::STATUS_ERROR,
            'progress' => JobInterface::STATUS_PROCESSING,
        ]);
    }

    /** @return AggregationInterface */
    public function getAggregations(): AggregationInterface
    {
        return $this->aggregations;
    }

    /**
     * @param AggregationInterface $aggregations
     *
     * @return void
     */
    public function setAggregations($aggregations): void
    {
        $this->aggregations = $aggregations;
    }

    /** @return SearchCriteriaInterface|null */
    public function getSearchCriteria(): ?SearchCriteriaInterface
    {
        return null;
    }

    /**
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return Collection
     */
    public function setSearchCriteria(?SearchCriteriaInterface $searchCriteria = null): Collection
    {
        return $this;
    }

    /** @return int */
    public function getTotalCount(): int
    {
        return $this->getSize();
    }

    /**
     * @param int $totalCount
     *
     * @return Collection
     */
    public function setTotalCount($totalCount): Collection
    {
        return $this;
    }

    /**
     * @param ExtensibleDataInterface[]|null $items
     *
     * @return Collection
     */
    public function setItems(?array $items = null): Collection
    {
        return $this;
    }
}
