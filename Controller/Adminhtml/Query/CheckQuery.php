<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Query;

use Algolia\AlgoliaSearch\Model\ResourceModel\Query as QueryResourceModel;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class CheckQuery extends \Magento\Backend\App\Action
{
    /** @var QueryResourceModel */
    protected $queryResourceModel;

    /**
     * @param Context $context
     * @param QueryResourceModel  $queryResourceModel
     */
    public function __construct(
        Context $context,
        QueryResourceModel $queryResourceModel
    ) {
        parent::__construct($context);
        $this->queryResourceModel = $queryResourceModel;
    }

    /** @return \Magento\Framework\View\Result\Page */
    public function execute()
    {
        $storeId = (int) $this->getRequest()->getParam('store_id');
        $queryId = (int) $this->getRequest()->getParam('query_id');
        $queryText = (string) $this->getRequest()->getParam('query_text');

        $queryId = $this->queryResourceModel->checkQueryUnicity($queryText, $storeId, $queryId);
        $responseContent = ['isValid' => $queryId ? false : true];

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($responseContent);

        return $resultJson;
    }
}
