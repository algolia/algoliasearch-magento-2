<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Magento\Framework\Controller\ResultFactory;

class Log extends \Magento\Backend\App\Action
{
    /** @return \Magento\Framework\View\Result\Page */
    public function execute()
    {
        $breadMain = __('Algolia | Indexing Queue Logs');

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Algolia_AlgoliaSearch::manage');
        $resultPage->getConfig()->getTitle()->prepend($breadMain);

        return $resultPage;
    }
}
