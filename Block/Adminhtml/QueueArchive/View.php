<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\QueueArchive;

use Magento\Backend\Block\Widget\Button;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class View extends Template
{
    /** @var SessionManagerInterface */
    protected $backendSession;

    public function __construct(
        Context          $context,
        SessionManagerInterface   $backendSession,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->backendSession = $backendSession;
    }

    /**
     * @inheritdoc
     */
    protected function _prepareLayout()
    {
        /** @var Button $button */
        $button = $this->getLayout()->createBlock(Button::class);
        $button->setData(
            [
                'label' => __('Back'),
                'onclick' => 'setLocation(\'' . $this->getBackUrl() . '\')',
                'class' => 'back',
            ]
        );

        $this->getToolbar()->setChild('back_button', $button);

        return parent::_prepareLayout();
    }

    /**
     * @return \Algolia\AlgoliaSearch\Model\QueueArchive
     */
    public function getCurrentJob()
    {
        return $this->backendSession->getData('current_job');
    }

    /**
     * @return string
     */
    public function getBackUrl()
    {
        return $this->getUrl('*/*/index');
    }

    /**
     * Return toolbar block instance
     *
     * @return bool|\Magento\Framework\View\Element\Template
     */
    public function getToolbar()
    {
        return $this->getLayout()->getBlock('page.actions.toolbar');
    }
}
