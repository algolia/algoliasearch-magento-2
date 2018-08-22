<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Reindex;

use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;

class Index extends \Magento\Backend\App\Action
{
    /**
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $breadMain = __('Algolia Reindex');

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->prepend($breadMain);

        return $resultPage;
    }
}
