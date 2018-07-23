<?php
/**
 * Module Algolia Algoliasearch
 */
namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Reindex;

use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;

class Index extends \Magento\Backend\App\Action
{
    /**
     * Execute the action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $breadMain = __('Reindex SKU(s)');

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Algolia_AlgoliaSearch::manage');
        $resultPage->getConfig()->getTitle()->prepend($breadMain);

        return $resultPage;
    }
}
