<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\IndexingManager;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DataObject;

class Reindex extends Action
{
    public function execute()
    {
        $storeId = $this->getRequest()->getParam("store_id");
        $entity = $this->getRequest()->getParam("entity");

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $this->messageManager->addSuccessMessage("Reindex successful (Store: $storeId, entities: $entity)");
        return $resultRedirect->setPath('*/*/');
    }
}

