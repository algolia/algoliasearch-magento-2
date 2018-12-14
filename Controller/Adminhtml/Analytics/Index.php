<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Analytics;

use Magento\Framework\Controller\ResultFactory;

class Index extends AbstractAction
{
    /**
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $breadMain = __('Algolia | Analytics Overview');

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->set($breadMain);

        return $resultPage;
    }
}
