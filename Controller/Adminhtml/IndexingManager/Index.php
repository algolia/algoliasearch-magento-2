<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\IndexingManager;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;

class Index extends Action
{
    /** @var ResultFactory */
    protected $resultFactory;

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
        $this->resultFactory = $context->getResultFactory();
    }

    /**
     * @return Page
     */
    public function execute()
    {
        $breadMain = __('Algolia | Indexing Manager');

        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->set($breadMain);

        return $resultPage;
    }
}
