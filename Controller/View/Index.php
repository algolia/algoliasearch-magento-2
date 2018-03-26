<?php

namespace Algolia\AlgoliaSearch\Controller\View;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    /** @var \Magento\Framework\View\Result\PageFactory */
    private $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultPageFactory->create();
        $result->addHandle('algolia');

        return $result;
    }
}
