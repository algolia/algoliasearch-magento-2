<?php

namespace Algolia\AlgoliaSearch\Controller\Landingpage;

use Algolia\AlgoliaSearch\Helper\LandingPageHelper;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;

class View extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    /** @var \Magento\Framework\Controller\Result\ForwardFactory */
    protected $resultForwardFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\ForwardFactory $resultForwardFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\ForwardFactory $resultForwardFactory
    ) {
        $this->resultForwardFactory = $resultForwardFactory;
        parent::__construct($context);
    }

    /**
     * View CMS page action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $pageId = $this->getRequest()->getParam('landing_page_id');

        $resultPage = $this->_objectManager->get(LandingPageHelper::class)->prepareResultPage($this, $pageId);
        if (!$resultPage) {
            $resultForward = $this->resultForwardFactory->create();

            return $resultForward->forward('noroute');
        }

        return $resultPage;
    }
}
