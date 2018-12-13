<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Support;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

abstract class AbstractAction extends \Magento\Backend\App\Action
{
    /** @var ResultFactory */
    protected $resultFactory;

    /**
     * @param Context $context
     * @param ResultFactory $resultFactory
     */
    public function __construct(
        Context $context,
        ResultFactory $resultFactory
    ) {
        parent::__construct($context);

        $this->resultFactory = $resultFactory;
    }

    /** @return bool */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Algolia_AlgoliaSearch::manage');
    }
}
