<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Query;

use Algolia\AlgoliaSearch\Model\Query;
use Magento\Framework\Controller\ResultFactory;

class Delete extends AbstractAction
{
    /** @return \Magento\Framework\View\Result\Page */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $queryId = $this->getRequest()->getParam('id');
        if ($queryId) {
            try {
                /** @var Query $query */
                $query = $this->queryFactory->create();
                $query->getResource()->load($query, $queryId);
                $query->getResource()->delete($query);
                $this->deleteQueryRules($query);

                $this->messageManager->addSuccessMessage(__('The query has been deleted.'));

                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());

                return $resultRedirect->setPath('*/*/edit', ['query_id' => $queryId]);
            }
        }
        $this->messageManager->addErrorMessage(__('The query to delete does not exist.'));

        return $resultRedirect->setPath('*/*/');
    }

    /**
     * @param Query $query
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function deleteQueryRules(Query $query): void
    {
        $stores = [];
        if ($query->getStoreId() == 0) {
            $stores = $this->getActiveStores();
        } else {
            $stores[] = $query->getStoreId();
        }

        foreach ($stores as $storeId) {
            $this->merchandisingHelper->deleteQueryRule(
                $storeId,
                $query->getId(),
                'query'
            );
        }
    }
}
