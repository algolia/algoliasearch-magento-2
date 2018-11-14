<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Support\Components;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Module\ModuleListInterface;

class LegacyVersion extends Template
{
    /** @var Context */
    private $backendContext;

    /** @var ModuleListInterface */
    private $moduleList;

    /**
     * @param Context $context
     * @param ModuleListInterface $moduleList
     * @param array $data
     */
    public function __construct(
        Context $context,
        ModuleListInterface $moduleList,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->backendContext = $context;
        $this->moduleList = $moduleList;
    }

    public function getExtensionVersion()
    {
        return $this->moduleList->getOne('Algolia_AlgoliaSearch')['setup_version'];
    }
}
