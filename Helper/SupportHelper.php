<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResourceModel;
use Algolia\AlgoliaSearch\Model\ResourceModel\NoteBuilder;

class SupportHelper
{
    const INTERNAL_API_PROXY_URL = 'https://magento-proxy.algolia.com/';

    /** @var ConfigHelper */
    private $configHelper;

    /** @var ProxyHelper */
    private $proxyHelper;

    /** @var JobResourceModel */
    private $jobResourceModel;

    /** @var NoteBuilder */
    private $noteBuilder;

    /**
     * @param ConfigHelper $configHelper
     * @param ProxyHelper $proxyHelper
     * @param JobResourceModel $jobResourceModel
     * @param NoteBuilder $noteBuilder
     */
    public function __construct(
        ConfigHelper $configHelper,
        ProxyHelper $proxyHelper,
        JobResourceModel $jobResourceModel,
        NoteBuilder $noteBuilder
    ) {
        $this->configHelper = $configHelper;
        $this->proxyHelper = $proxyHelper;
        $this->jobResourceModel = $jobResourceModel;
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
            'note' => $this->getNoteData($data['send_additional_info']),
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
     * @param bool $sendAdditionalData
     *
     * @throws \Zend_Db_Statement_Exception
     *
     * @return array
     */
    private function getNoteData($sendAdditionalData = false)
    {
        $queueInfo = $this->jobResourceModel->getQueueInfo();

        $noteData = [
            'extension_version' => $this->getExtensionVersion(),
            'magento_version' => $this->configHelper->getMagentoVersion(),
            'magento_edition' => $this->configHelper->getMagentoEdition(),
            'queue_jobs_count' => $queueInfo['count'],
            'queue_oldest_job' => $queueInfo['oldest'],
            'queue_archive_rows' => $this->noteBuilder->getQueueArchiveInfo(),
            'algolia_configuration' => $this->noteBuilder->getAlgoliaConfiguration(),
        ];

        if ($sendAdditionalData === true) {
            $noteData['catalog_info'] = $this->noteBuilder->getCatalogInfo();
            $noteData['modules'] = $this->noteBuilder->get3rdPartyModules();
        }

        return $noteData;
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
