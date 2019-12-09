<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use GuzzleHttp\Client;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class ExtensionNotification
{
    const CHECK_FREQUENCY = 86400; // one day

    const REPOSITORY_URI = '/repos/algolia/algoliasearch-magento-2/releases/latest';

    const APPLICATION_JSON_HEADER = ['Content-Type' => 'application/json'];

    /** @var CacheInterface */
    protected $cacheManager;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var LoggerInterface */
    private $logger;

    /** @var Client */
    private $guzzleClient;

    private $repoData = null;

    /**
     * @param CacheInterface $cacheManager
     * @param ConfigHelper $configHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        CacheInterface $cacheManager,
        ConfigHelper $configHelper,
        LoggerInterface $logger
    ) {
        $this->cacheManager = $cacheManager;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->guzzleClient = new Client([
            'base_uri' => 'https://api.github.com',
            'timeout' => 10.0,
        ]);
    }

    /**
     * Check the version using a one day cache and return the data if there's a new version
     *
     * @return array|null
     */
    public function checkVersion()
    {
        // First, try to fetch last check and return it if the frequency is not outdated
        $lastCheck = $this->getLastCheck();
        if ($lastCheck['time'] + self::CHECK_FREQUENCY > time()) {
            // Return it only if the version is new
            return $lastCheck['is_new'] ? $lastCheck : null;
        }

        return $this->checkExtensionVersion();
    }

    /**
     * Retrieve Last check time
     *
     * @return int
     */
    private function getLastCheck()
    {
        $notificationData = json_decode(
            $this->cacheManager->load('algoliasearch_notification_lastcheck'),
            true
        );
        if ($notificationData === null || !is_array($notificationData)) {
            $notificationData = [
                'time' => 0,
                'is_new' => false,
                'version' => '',
                'url' => '',
            ];
        }

        return $notificationData;
    }

    /**
     * Set last check (time, version and url)
     *
     * @param array $newExtensionData
     *
     * @return $this
     */
    private function setLastCheck($newExtensionData)
    {
        $this->cacheManager->save(json_encode($newExtensionData), 'algoliasearch_notification_lastcheck');

        return $this;
    }

    private function checkExtensionVersion()
    {
        $newVersion = null;
        try {
            $versionFromRepository = $this->getLatestVersionFromRepository()->name;
        } catch (\Exception $e) {
            $this->logger->log(LogLevel::INFO, $e->getMessage());
            return $newVersion;
        }

        $newVersion = [
            'time' => time(),
            'is_new' => false,
            'version' => $versionFromRepository,
            'url' => $this->getLatestVersionFromRepository()->html_url,
        ];

        $versionFromDb = $this->configHelper->getExtensionVersion();
        // If the db version is older than the repo one, mark it as new and return it
        if (version_compare($versionFromDb, $versionFromRepository, '<')) {
            $newVersion['is_new'] = true;
            $this->setLastCheck($newVersion);

            return $newVersion;
        }

        $this->setLastCheck($newVersion);

        return null;
    }

    private function getLatestVersionFromRepository()
    {
        if ($this->repoData === null) {
            $response = $this->guzzleClient->request(
                'GET',
                self::REPOSITORY_URI,
                [
                    'headers' => self::APPLICATION_JSON_HEADER,
                ]
            );

            if ($response->getStatusCode() != 200) {
                throw new \Exception($response->getReasonPhrase());
            }

            $this->repoData = json_decode($response->getBody()->getContents());
        }

        return $this->repoData;
    }
}
