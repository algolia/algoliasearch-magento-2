<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Support;

use Algolia\AlgoliaSearch\Helper\SupportHelper;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;

class Contact extends AbstractAction
{
    private $supportHelper;

    public function __construct(Context $context, ResultFactory $resultFactory, SupportHelper $supportHelper)
    {
        parent::__construct($context, $resultFactory);

        $this->supportHelper = $supportHelper;
    }

    /** @return Redirect | Page */
    public function execute()
    {
        if ($this->supportHelper->isExtensionSupportEnabled() === false) {
            $this->messageManager->addErrorMessage('Your Algolia app is not eligible for e-mail support.');

            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('*/*/index');

            return $resultRedirect;
        }

        $params = $this->getRequest()->getParams();
        if (array_key_exists('sent', $params) && $params['sent'] === 'sent') {
            $processed = $this->supportHelper->processContactForm($params);
            if ($processed === true) {
                $this->messageManager->addSuccessMessage('You ticket was successfully sent to Algolia support team.');

                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('*/*/index');

                return $resultRedirect;
            }

            $this->messageManager->addErrorMessage('There was an error while sending your ticket. Please, try it again.');
        }

        $breadMain = __('Algolia | Contact Us');

        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->prepend($breadMain);

        return $resultPage;
    }
}
