<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Support;

use Algolia\AlgoliaSearch\Helper\SupportHelper;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Module\ModuleListInterface;

class Contact extends Template
{
    /** @var Context */
    private $backendContext;

    /** @var SupportHelper */
    private $supportHelper;

    /** @var ModuleListInterface */
    private $moduleList;

    private $authSession;

    /**
     * @param Context $context
     * @param SupportHelper $supportHelper
     * @param ModuleListInterface $moduleList
     * @param Session $authSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        SupportHelper $supportHelper,
        ModuleListInterface $moduleList,
        Session $authSession,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->backendContext = $context;
        $this->supportHelper = $supportHelper;
        $this->moduleList = $moduleList;
        $this->authSession = $authSession;
    }

    /** @return bool */
    public function isExtensionSupportEnabled()
    {
        return $this->supportHelper->isExtensionSupportEnabled();
    }

    /** @return string */
    public function getExtensionVersion()
    {
        return $this->moduleList->getOne('Algolia_AlgoliaSearch')['setup_version'];
    }

    /** @return string */
    public function getDefaultName()
    {
        $name = $this->getRequest()->getParam('name');

        return $name ?: $this->getCurrenctAdmin()->getName();
    }

    /** @return string */
    public function getDefaultEmail()
    {
        $name = $this->getRequest()->getParam('email');

        return $name ?: $this->getCurrenctAdmin()->getEmail();
    }

    /** @return \Magento\User\Model\User|null */
    private function getCurrenctAdmin()
    {
        return $this->authSession->getUser();
    }
}
