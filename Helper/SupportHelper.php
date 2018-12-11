<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Model\ResourceModel\NoteBuilder;

class SupportHelper
{
    /** @var ConfigHelper */
    private $configHelper;

    /** @var ProxyHelper */
    private $proxyHelper;

    /** @var NoteBuilder */
    private $noteBuilder;

    /**
     * @param ConfigHelper $configHelper
     * @param ProxyHelper $proxyHelper
     * @param NoteBuilder $noteBuilder
     */
    public function __construct(
        ConfigHelper $configHelper,
        ProxyHelper $proxyHelper,
        NoteBuilder $noteBuilder
    ) {
        $this->configHelper = $configHelper;
        $this->proxyHelper = $proxyHelper;
        $this->noteBuilder = $noteBuilder;
    }

    /** @return string */
    public function getApplicationId()
    {
        return $this->configHelper->getApplicationID();
    }

    /** @return string */
    public function getExtensionVersion()
    {
        return $this->configHelper->getExtensionVersion();
    }

    /**
     * @param array $data
     *
     * @throws \Zend_Db_Statement_Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return bool
     */
    public function processContactForm($data)
    {
        list($firstname, $lastname) = $this->splitName($data['name']);

        $messageData = [
            'email' => $data['email'],
            'firstname' => $firstname,
            'lastname' => $lastname,
            'subject' => $data['subject'],
            'text' => $data['message'],
            'note' => $this->noteBuilder->getNote($data['send_additional_info']),
        ];

        return $this->proxyHelper->pushSupportTicket($messageData);
    }

    /** @return bool */
    public function isExtensionSupportEnabled()
    {
        $info = $this->proxyHelper->getInfo(ProxyHelper::INFO_TYPE_EXTENSION_SUPPORT);

        // In case the call to API proxy fails,
        // be "nice" and return true
        if ($info && array_key_exists('extension_support', $info)) {
            return $info['extension_support'];
        }

        return true;
    }

    /**
     * @param string $name
     *
     * @return array
     */
    private function splitName($name)
    {
        return explode(' ', $name, 2);
    }
}
