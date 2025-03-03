<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\IndexingManager;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Algolia\AlgoliaSearch\Service\Category\QueueBuilder as CategoryQueueBuilder;
use Algolia\AlgoliaSearch\Service\Page\QueueBuilder as PageQueueBuilder;
use Algolia\AlgoliaSearch\Service\Product\QueueBuilder as ProductQueueBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class Reindex extends Action
{
    public function __construct(
        Context $context,
        protected StoreManagerInterface $storeManager,
        protected ProductQueueBuilder $productQueueBuilder,
        protected CategoryQueueBuilder $categoryQueueBuilder,
        protected PageQueueBuilder $pageQueueBuilder,
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
            $queueBuilder = match ($entity) {
                'products' => $this->productQueueBuilder,
                'categories' => $this->categoryQueueBuilder,
                'pages' => $this->pageQueueBuilder,
                default => throw new AlgoliaException('Unknown entity to index.'),
            };

            foreach ($storeIds as $storeId) {
                $queueBuilder->buildQueue($storeId);
                $this->messageManager->addSuccessMessage("Reindex successful (Store: $storeId, entities: $entity)");
            }
        }
    }
}

