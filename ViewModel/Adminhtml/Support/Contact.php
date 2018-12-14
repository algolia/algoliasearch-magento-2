<?php

namespace Algolia\AlgoliaSearch\ViewModel\Adminhtml\Support;

use Algolia\AlgoliaSearch\Helper\SupportHelper;
use Algolia\AlgoliaSearch\ViewModel\Adminhtml\BackendView;
use Magento\Backend\Block\Template;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\User\Model\User;

class Contact implements ArgumentInterface
{
    /** @var BackendView */
    private $backendView;

    /** @var SupportHelper */
    private $supportHelper;

    /** @var ModuleListInterface */
    private $moduleList;

    /** @var Session */
    private $authSession;

    /**
     * @param BackendView $backendView
     * @param SupportHelper $supportHelper
     * @param ModuleListInterface $moduleList
     * @param Session $authSession
     */
    public function __construct(
        BackendView $backendView,
        SupportHelper $supportHelper,
        ModuleListInterface $moduleList,
        Session $authSession
    ) {
        $this->backendView = $backendView;
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
        $name = $this->backendView->getRequest()->getParam('name');

        return $name ?: $this->getCurrenctAdmin()->getName();
    }

    /** @return string */
    public function getDefaultEmail()
    {
        $name = $this->backendView->getRequest()->getParam('email');

        return $name ?: $this->getCurrenctAdmin()->getEmail();
    }

    /**
     * @param string $message
     *
     * @return string
     */
    public function getTooltipHtml($message)
    {
        return $this->backendView->getTooltipHtml($message);
    }

    /**
     * @return string
     */
    public function getLegacyVersionHtml()
    {
        /** @var Template $block */
        $block = $this->backendView->getLayout()->createBlock(Template::class);

        $block->setTemplate('Algolia_AlgoliaSearch::support/components/legacy-version.phtml');
        $block->setData('extension_version', $this->getExtensionVersion());

        return $block->toHtml();
    }

    /** @return User|null */
    private function getCurrenctAdmin()
    {
        return $this->authSession->getUser();
    }
}
