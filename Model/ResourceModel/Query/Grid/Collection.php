<?php

namespace Algolia\AlgoliaSearch\Model\ResourceModel\Query\Grid;

use Algolia\AlgoliaSearch\Model\ResourceModel\Query\Collection as QueryCollection;
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

class Collection extends QueryCollection implements SearchResultInterface
{
    protected AggregationInterface $aggregations;

    /**
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
    }

    public function getAggregations(): AggregationInterface
    {
        return $this->aggregations;
    }

    /**
     * @param AggregationInterface $aggregations (compatibility with SearchResultInterface::setAggregations()
     */
    public function setAggregations($aggregations): void
    {
        $this->aggregations = $aggregations;
    }

    public function getSearchCriteria(): ?SearchCriteriaInterface
    {
        return null;
    }

    public function setSearchCriteria(?SearchCriteriaInterface $searchCriteria = null): self
    {
        return $this;
    }

    public function getTotalCount(): int
    {
        return $this->getSize();
    }

    /**
     * @param int $totalCount (compatibility with SearchResultsInterface::setTotalCount())
     */
    public function setTotalCount($totalCount): self
    {
        return $this;
    }

    /**
     * @param ExtensibleDataInterface[]|null $items
     */
    public function setItems(?array $items = null): self
    {
        return $this;
    }
}
