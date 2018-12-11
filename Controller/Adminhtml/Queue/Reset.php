<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Magento\Framework\Controller\ResultFactory;

class Reset extends AbstractAction
{
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('*/*/index');

        try {
            $queueRunnerIndexer = $this->indexerFactory->create();
            $queueRunnerIndexer->load(\Algolia\AlgoliaSearch\Model\Indexer\QueueRunner::INDEXER_ID);
            $queueRunnerIndexer->getState()->setStatus(\Magento\Framework\Indexer\StateInterface::STATUS_VALID);
            $queueRunnerIndexer->getState()->save();
            $this->messageManager->addSuccessMessage(__('Queue has been reset.'));
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e);
        }

        return $resultRedirect;
    }
}
