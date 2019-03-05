<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Merchandising;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\LayoutFactory;

class Index extends \Magento\Backend\App\Action
{

    /** @var ResultFactory */
    protected $resultFactory;

    /** @var LayoutFactory */
    protected $layoutFactory;

    /**
     * @param Context $context
     * @param LayoutFactory $layoutFactory
     */
    public function __construct(Context $context, LayoutFactory $layoutFactory)
    {
        parent::__construct($context);

        $this->resultFactory = $context->getResultFactory();
        $this->layoutFactory = $layoutFactory;
    }

    /**
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $breadMain = __('Algolia | Merchandising');

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->set($breadMain);

        return $resultPage;
    }
}
