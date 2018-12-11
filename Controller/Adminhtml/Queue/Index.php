<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class Index extends \Magento\Backend\App\Action
{
    /** @var Algolia\AlgoliaSearch\Helper\ConfigHelper */
    protected $configHelper;

    /**
     * @param Context       $context
     * @param ConfigHelper  $configHelper
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper
    ) {
        parent::__construct($context);
        $this->configHelper = $configHelper;
    }

    /** @return \Magento\Framework\View\Result\Page */
    public function execute()
    {
        $breadMain = __('Algolia Indexing Queue');

        $this->checkQueueIsActivated();

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Algolia_AlgoliaSearch::manage');
        $resultPage->getConfig()->getTitle()->prepend($breadMain);

        return $resultPage;
    }

    protected function checkQueueIsActivated()
    {
        if (! $this->configHelper->isQueueActive()) {
            $msg = __(
                'The indexing queue is not activated. Please activate it in the <a href="%1">Algolia configuration</a>.',
                $this->getUrl('adminhtml/system_config/edit/section/algoliasearch_queue')
            );
            $this->messageManager->addWarning($msg);
        }
    }
}
