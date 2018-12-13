<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Support;

use Algolia\AlgoliaSearch\Helper\SupportHelper;
use Magento\Backend\Block\Template\Context;

class Index extends AbstractSupportTemplate
{
    /** @var Context */
    private $backendContext;

    /** @var SupportHelper */
    private $supportHelper;

    /**
     * @param Context $context
     * @param SupportHelper $supportHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        SupportHelper $supportHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->backendContext = $context;
        $this->supportHelper = $supportHelper;
    }

    /** @return bool */
    public function isExtensionSupportEnabled()
    {
        return $this->supportHelper->isExtensionSupportEnabled();
    }

    /** @return string */
    public function getApplicationId()
    {
        return $this->supportHelper->getApplicationId();
    }
}
