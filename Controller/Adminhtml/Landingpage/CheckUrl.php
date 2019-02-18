<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Landingpage;

use Algolia\AlgoliaSearch\Model\ResourceModel\LandingPage as LandingPageResourceModel;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class CheckUrl extends \Magento\Backend\App\Action
{
    /** @var LandingPageResourceModel */
    protected $landingPageResourceModel;

    /**
     * @param Context       $context
     * @param LandingPageResourceModel  $landingPageResourceModel
     */
    public function __construct(
        Context $context,
        LandingPageResourceModel $landingPageResourceModel
    ) {
        parent::__construct($context);
        $this->landingPageResourceModel = $landingPageResourceModel;
    }

    /** @return \Magento\Framework\View\Result\Page */
    public function execute()
    {
        $storeId = (int) $this->getRequest()->getParam('store_id');
        $landingPageId = (int) $this->getRequest()->getParam('landing_page_id');
        $urlKey = (string) $this->getRequest()->getParam('url_key');

        $urlRwriteId = $this->landingPageResourceModel->checkUrlRewriteTable($urlKey, $storeId, $landingPageId);
        $responseContent = ['isValid' => $urlRwriteId ? false : true];

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($responseContent);

        return $resultJson;
    }
}
