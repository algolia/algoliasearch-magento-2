<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class Index extends \Magento\Backend\App\Action
{
    /** @var Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper */
    protected $queueHelper;

    public function __construct(
        Context $context,
        QueueHelper $queueHelper
    ) {
        parent::__construct($context);
        $this->queueHelper = $queueHelper;
    }

    /**
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $breadMain = __('Algolia | Indexing Queue overview');

        $this->checkQueueIsActivated();

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Algolia_AlgoliaSearch::manage');
        $resultPage->getConfig()->getTitle()->prepend($breadMain);

        return $resultPage;
    }

    protected function checkQueueIsActivated()
    {
        if (! $this->queueHelper->isQueueActive()) {
            $msg = __(
                'The indexing queue is not activated. Please activate it in the <a href="%1">Algolia configuration</a>.',
                $this->getUrl('adminhtml/system_config/edit/section/algoliasearch_queue')
            );
            $this->messageManager->addWarning($msg);
        }
    }
}
