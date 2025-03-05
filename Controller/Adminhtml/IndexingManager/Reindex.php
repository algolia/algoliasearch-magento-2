<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\IndexingManager;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
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
        $storeIds = $this->getRequest()->getParam("store_id") === '0' ?
            array_keys($this->storeManager->getStores()) :
            [(int) $this->getRequest()->getParam("store_id")];

        $entities = $this->getRequest()->getParam("entity") === 'all' ?
            ['products', 'categories', 'pages'] :
            [$this->getRequest()->getParam("entity")];

        $this->reindexEntities($entities, $storeIds);

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('*/*/');
    }

    /**
     * @param array $entities
     * @param array|null $storeIds
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException|NoSuchEntityException|DiagnosticsException
     */
    protected function reindexEntities(array $entities, array $storeIds = null): void
    {
        foreach ($entities as $entity) {
            $processor = match ($entity) {
                'products' => $this->productBatchQueueProcessor,
                'categories' => $this->categoryBatchQueueProcessor,
                'pages' => $this->pageBatchQueueProcessor,
                default => throw new AlgoliaException('Unknown entity to index.'),
            };

            foreach ($storeIds as $storeId) {
                $processor->processBatch($storeId);
                $this->messageManager->addSuccessMessage("Reindex successful (Store: $storeId, entities: $entity)");
            }
        }
    }
}

