<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Support\Components;

use Algolia\AlgoliaSearch\Helper\SupportHelper;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class LegacyVersion extends Template
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

        $this->setTemplate('Algolia_AlgoliaSearch::support/components/legacy-version.phtml');

        $this->backendContext = $context;
        $this->supportHelper = $supportHelper;
    }

    public function getExtensionVersion()
    {
        return $this->supportHelper->getExtensionVersion();
    }
}
