<?php

namespace Algolia\AlgoliaSearch\Model\Observer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Notification\NotifierInterface;

class ExtensionNotification implements ObserverInterface
{
    const CHECK_FREQUENCY = 604800; // one week

    const REPOSITORY_URL = 'https://api.github.com/repos/algolia/algoliasearch-magento-2/releases/latest';

    const CURL_OPT = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: PHP',
            ],
        ],
    ];

    /** @var NotifierInterface */
    private $notifier;

    /** @var CacheInterface */
    protected $cacheManager;

    /** @var ConfigHelper */
    private $configHelper;

    private $repoData = null;

    /**
     * @param NotifierInterface $notifier
     * @param CacheInterface $cacheManager
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        NotifierInterface $notifier,
        CacheInterface $cacheManager,
        ConfigHelper $configHelper
    ) {
        $this->notifier = $notifier;
        $this->cacheManager = $cacheManager;
        $this->configHelper = $configHelper;
    }

    /**
     * @param Observer $observer
     *
     */
    public function execute(Observer $observer)
    {
        if ($this->getLastCheck() + self::CHECK_FREQUENCY > time()) {
            return;
        }

        $this->checkExtensionVersion();
        $this->setLastCheck();
    }

    /**
     * Retrieve Last check time
     *
     * @return int
     */
    private function getLastCheck()
    {
        return $this->cacheManager->load('algoliasearch_notification_lastcheck');
    }

    /**
     * Set last check time (now)
     *
     * @return $this
     */
    private function setLastCheck()
    {
        $this->cacheManager->save(time(), 'algoliasearch_notification_lastcheck');

        return $this;
    }

    /**
     * Retrieve Last checked version
     *
     * @return string
     */
    private function getLastCheckedVersion()
    {
        return $this->cacheManager->load('algoliasearch_notification_lastchecked_version');
    }

    /**
     * Set last checked version
     *
     * @param $version
     *
     * @return $this
     */
    private function setLastCheckedVersion($version)
    {
        $this->cacheManager->save($version, 'algoliasearch_notification_lastchecked_version');

        return $this;
    }

    private function checkExtensionVersion()
    {
        $versionFromRepository = $this->getLatestVersionFromRepository()->name;
        $versionFromDb = $this->configHelper->getExtensionVersion();
        $lastCheckedVersion = $this->getLastCheckedVersion() !== false ? $this->getLastCheckedVersion() : '0.0.0';

        if (version_compare($versionFromDb, $versionFromRepository, '>=') ||
            version_compare($lastCheckedVersion, $versionFromRepository, '>=')) {
            return;
        }

        $this->notifier->addNotice(
            'Algolia Extension update',
            'New extension release (v ' . $versionFromRepository . ')',
            $this->getLatestVersionFromRepository()->html_url,
            false
        );

        // Cache the last checked version from the repository (to prevent the notification to be sent more than once)
        $this->setLastCheckedVersion($versionFromRepository);
    }

    private function getLatestVersionFromRepository()
    {
        if ($this->repoData === null) {
            $json = file_get_contents(
                self::REPOSITORY_URL,
                false,
                stream_context_create(self::CURL_OPT)
            );
            $this->repoData = json_decode($json);
        }

        return $this->repoData;
    }
}
