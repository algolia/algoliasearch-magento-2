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

    /**
     * @throws \Zend_Db_Statement_Exception
     *
     * @return Redirect | Page
     */
    public function execute()
    {
        if ($this->supportHelper->isExtensionSupportEnabled() === false) {
            $this->messageManager->addErrorMessage('Your Algolia app is not eligible for e-mail support.');

            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('*/*/index');

            return $resultRedirect;
        }

        $params = $this->getRequest()->getParams();
        if ($this->isFormSubmitted($params) && $data = $this->validateForm($params)) {
            $processed = $this->supportHelper->processContactForm($data);
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

    /**
     * @param array $data
     *
     * @return bool
     */
    private function isFormSubmitted($data)
    {
        return array_key_exists('sent', $data) && $data['sent'] === 'sent';
    }

    /**
     * @param array $data
     *
     * @return array|bool
     */
    private function validateForm($data)
    {
        $attributes = [
            'name' => 'Name',
            'email' => 'E-mail',
            'subject' => 'Subject',
            'message' => 'Message',
        ];

        $cleanData = [];
        $isValid = true;

        foreach ($attributes as $attribute => $caption) {
            $value = trim($data[$attribute]);

            if ($value === '') {
                $isValid = false;
                $this->messageManager->addErrorMessage('Please, fill in your "' . $caption . '".');

                continue;
            }

            if ($attribute === 'email' && $this->validateEmail($value) === false) {
                $isValid = false;
                $this->messageManager->addErrorMessage('Please enter a valid e-mail address (Ex: johndoe@domain.com).');

                continue;
            }

            $cleanData[$attribute] = $value;
        }

        if ($isValid === false) {
            return false;
        }

        $cleanData['send_additional_info'] = false;
        if (array_key_exists('send_additional_info', $data) && $data['send_additional_info'] === '1') {
            $cleanData['send_additional_info'] = true;
        }

        return $cleanData;
    }

    /**
     * @param string $email
     *
     * @return bool
     */
    private function validateEmail($email)
    {
        $atom = "[-a-z0-9!#$%&'*+/=?^_`{|}~]";
        $alpha = "a-z\x80-\xFF";

        $isValid = (bool) preg_match("(^
            (\"([ !#-[\\]-~]*|\\\\[ -~])+\"|$atom+(\\.$atom+)*)
            @
            ([0-9$alpha]([-0-9$alpha]{0,61}[0-9$alpha])?\\.)+
            [$alpha]([-0-9$alpha]{0,17}[$alpha])?\\z)ix", $email);

        return $isValid;
    }
}
