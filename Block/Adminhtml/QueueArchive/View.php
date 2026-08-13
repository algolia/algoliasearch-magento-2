<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\QueueArchive;

use Algolia\AlgoliaSearch\Api\Data\QueueArchiveInterface;
use Algolia\AlgoliaSearch\Api\QueueArchiveRepositoryInterface;
use Magento\Backend\Block\Widget\Button;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class View extends Template
{
    protected ?QueueArchiveInterface $currentJob = null;

    public function __construct(
        Context                                   $context,
        protected QueueArchiveRepositoryInterface $queueArchiveRepository,
        array                                      $data = []
    ) {
        parent::__construct($context, $data);
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
     * @throws NoSuchEntityException
     */
    public function getCurrentJob(): QueueArchiveInterface
    {
        return $this->currentJob ??= $this->queueArchiveRepository->getById(
            (int) $this->getRequest()->getParam('id')
        );
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
