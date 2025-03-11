<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\IndexingManager;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Algolia\AlgoliaSearch\Service\Category\BatchQueueProcessor as CategoryBatchQueueProcessor;
use Algolia\AlgoliaSearch\Service\Page\BatchQueueProcessor as PageBatchQueueProcessor;
use Algolia\AlgoliaSearch\Service\Product\BatchQueueProcessor as ProductBatchQueueProcessor;
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
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $storeIds = is_null($this->getRequest()->getParam("store_id")) ||  $this->getRequest()->getParam("store_id") === '0' ?
            array_keys($this->storeManager->getStores()) :
            [(int) $this->getRequest()->getParam("store_id")];

        $entities = $this->defineEntitiesToIndex();
        $entityIds = !is_null($this->getRequest()->getParam('selected')) ?
            $this->getRequest()->getParam('selected') :
            null;

        $this->reindexEntities($entities, $storeIds, $entityIds);

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath($this->defineRedirectPath());
    }

    /**
     * @return array|string[]
     */
    protected function defineEntitiesToIndex(): array
    {
        $entities = [];
        $params = $this->getRequest()->getParams();
        if (isset($params["entity"])) {
            $entities = $params["entity"] === 'all' ?
                ['products', 'categories', 'pages'] :
                [$params["entity"]];
        } else if (isset($params["namespace"])) {
            $entities = match ($params["namespace"]) {
                'product_listing' => ['products'],
                'cms_page_listing' => ['pages'],
                default => []
            };
        }

        return $entities;
    }

    /**
     * @return string
     */
    protected function defineRedirectPath(): string
    {
        $redirect = '*/*/';

        $params = $this->getRequest()->getParams();
        if (isset($params["namespace"])) {
            $redirect = match ($params["namespace"]) {
                'product_listing' => 'catalog/product/index',
                'cms_page_listing' => 'cms/page/index',
                default => '*/*/'
            };
        }

        return $redirect;
    }

    /**
     * @param array $entities
     * @param array|null $storeIds
     * @param array|null $entityIds
     * @return void
     * @throws AlgoliaException
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    protected function reindexEntities(array $entities, array $storeIds = null, array $entityIds = null): void
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
