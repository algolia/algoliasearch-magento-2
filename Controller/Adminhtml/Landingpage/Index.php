<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Landingpage;

use Magento\Framework\Controller\ResultFactory;

class Index extends AbstractAction
{
    /** @return \Magento\Framework\View\Result\Page */
    public function execute()
    {
        $breadMain = __('Algolia | Landing Page Builder');

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Algolia_AlgoliaSearch::manage');
        $resultPage->getConfig()->getTitle()->prepend($breadMain);

        $dataPersistor = $this->_objectManager->get(\Magento\Framework\App\Request\DataPersistorInterface::class);
        $dataPersistor->clear('landing_page');

        return $resultPage;
    }

    /** @return bool */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Algolia_AlgoliaSearch::manage');
    }
}
