<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Support;

use Magento\Framework\Controller\ResultFactory;

class Index extends AbstractAction
{
    /**
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $breadMain = __('Algolia | Help & Support');

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->prepend($breadMain);

        return $resultPage;
    }
}
