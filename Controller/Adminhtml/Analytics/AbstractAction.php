<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Analytics;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\LayoutFactory;

abstract class AbstractAction extends \Magento\Backend\App\Action
{
    /** @var ResultFactory */
    protected $resultFactory;

    /** @var JsonFactory */
    protected $resultJsonFactory;

    /** @var LayoutFactory */
    protected $layoutFactory;

    /**
     * AbstractAction constructor.
     * @param Context $context
     * @param ResultFactory $resultFactory
     * @param JsonFactory $resultJsonFactory
     * @param LayoutFactory $layoutFactory
     */
    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        JsonFactory $resultJsonFactory,
        LayoutFactory $layoutFactory
    ) {
        parent::__construct($context);
        $this->resultFactory = $resultFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->layoutFactory = $layoutFactory;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Algolia_AlgoliaSearch::manage');
    }
}
