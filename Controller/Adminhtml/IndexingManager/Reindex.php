<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\IndexingManager;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Algolia\AlgoliaSearch\Service\Category\BatchQueueProcessor as CategoryBatchQueueProcessor;
use Algolia\AlgoliaSearch\Service\Page\BatchQueueProcessor as PageBatchQueueProcessor;
use Algolia\AlgoliaSearch\Service\Product\BatchQueueProcessor as ProductBatchQueueProcessor;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class Reindex extends Action
{
    public function __construct(
        Context $context,
        protected StoreManagerInterface $storeManager,
        protected StoreNameFetcher $storeNameFetcher,
        protected IndexNameFetcher $indexNameFetcher,
        protected ConfigHelper $configHelper,
        protected ProductBatchQueueProcessor $productBatchQueueProcessor,
        protected CategoryBatchQueueProcessor $categoryBatchQueueProcessor,
        protected PageBatchQueueProcessor $pageBatchQueueProcessor,
    ){
        parent::__construct($context);
    }

    /**
     * @throws ExceededRetriesException
     * @throws AlgoliaException
     * @throws NoSuchEntityException|DiagnosticsException
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $storeIds = !isset($params["store_id"]) || $params["store_id"] === (string) AlgoliaConnector::ALGOLIA_DEFAULT_SCOPE ?
            array_keys($this->storeManager->getStores()) :
            [(int) $params["store_id"]];

        $entities = $this->defineEntitiesToIndex($params);
        $entityIds = $params['selected'] ?? null;

        $this->reindexEntities($entities, $storeIds, $entityIds);

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath($this->defineRedirectPath($params));
    }

    /**
     * @return array|string[]
     */
    protected function defineEntitiesToIndex(array $params): array
    {
        $entities = [];
        if (isset($params["entity"])) {
            $entities = $this->isFullIndex($params) ?
                ['products', 'categories', 'pages'] :
                [$params["entity"]];
        } else if ($this->isMassAction($params)) {
            $entities = match ($params["namespace"]) {
                'product_listing' => ['products'],
                'cms_page_listing' => ['pages'],
                default => []
            };
        }

        return $entities;
    }

    /**
     * @param array $params
     * @return string
     */
    protected function defineRedirectPath(array $params): string
    {
        $redirect = '*/*/';

        if (isset($params["redirect"])) {
            return $params["redirect"];
        }

        if ($this->isMassAction($params)) {
            $redirect = match ($params["namespace"]) {
                'product_listing' => 'catalog/product/index',
                'cms_page_listing' => 'cms/page/index',
                default => '*/*/'
            };
        }

        return $redirect;
    }

    /**
     * Defines if all entities need to be reindex
     *
     * @param array $params
     * @return bool
     */
    protected function isFullIndex(array $params): bool
    {
        return isset($params["entity"]) && $params["entity"] === 'all';
    }

    /**
     * Check if the request is coming from a grid (products or pages)
     *
     * @param array $params
     * @return bool
     */
    protected function isMassAction(array $params): bool
    {
        return isset($params["namespace"]);
    }

    /**
     * @param array $entities
     * @param array|null $storeIds
     * @param array|null $entityIds
     * @return void
     * @throws AlgoliaException
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function reindexEntities(array $entities, ?array $storeIds = null, ?array $entityIds = null): void
    {
        foreach ($entities as $entity) {
            $processor = match ($entity) {
                'products' => $this->productBatchQueueProcessor,
                'categories' => $this->categoryBatchQueueProcessor,
                'pages' => $this->pageBatchQueueProcessor,
                default => throw new AlgoliaException('Unknown entity to index.'),
            };

            foreach ($storeIds as $storeId) {
                $processor->processBatch($storeId, $entityIds);
                $message = $this->storeNameFetcher->getStoreName($storeId) . " ";
                $message .= "(" . $this->indexNameFetcher->getIndexName('_' . $entity, $storeId);

                if (!is_null($entityIds)) {
                    $recordLabel = count($entityIds) > 1 ? "records" : "record";
                    $message .= " - " . count($entityIds) . " " . $recordLabel;
                } else {
                    $message .= " - full reindexing job";
                }

                if (!$this->configHelper->isQueueActive($storeId)) {
                    $message .= " successfully processed)";
                } else {
                    $message .= " successfully added to the Algolia indexing queue)";
                }

                $this->messageManager->addSuccessMessage(htmlentities(__($message)));
            }
        }
    }
}
