<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Api\SearchClientProviderInterface;
use Algolia\AlgoliaSearch\Configuration\SearchConfig;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Support\AlgoliaAgent;

class SearchClientProvider implements SearchClientProviderInterface
{
    /** @var SearchClient[] */
    protected array $clients = [];

    protected bool $userAgentsAdded = false;

    public function __construct(
        protected ConfigHelper $config,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    ) {}

    /**
     * @throws AlgoliaException
     */
    public function getClient(?int $storeId = self::ALGOLIA_DEFAULT_SCOPE): SearchClient
    {
        if ($storeId === null) {
            $storeId = self::ALGOLIA_DEFAULT_SCOPE;
        }

        if (!isset($this->clients[$storeId])) {
            $this->createClient($storeId);
            if (!$this->userAgentsAdded) {
                $this->addAlgoliaUserAgent($storeId);
            }
        }

        return $this->clients[$storeId];
    }

    /**
     * @throws AlgoliaException
     */
    protected function createClient(int $storeId = self::ALGOLIA_DEFAULT_SCOPE): void
    {
        if (!$this->algoliaCredentialsManager->checkCredentials($storeId)) {
            throw new AlgoliaException('Client initialization could not be performed because Algolia credentials were not provided.');
        }

        $config = SearchConfig::create(
            $this->config->getApplicationID($storeId),
            $this->config->getAPIKey($storeId)
        );
        $config->setConnectTimeout($this->getConnectionTimeout($storeId));
        $config->setReadTimeout($this->getReadTimeout($storeId));
        $config->setWriteTimeout($this->config->getWriteTimeout($storeId));
        $this->clients[$storeId] = SearchClient::createWithConfig($config);
    }

    /**
     * @throws AlgoliaException
     */
    protected function addAlgoliaUserAgent(int $storeId = self::ALGOLIA_DEFAULT_SCOPE): void
    {
        $clientName = $this->getClient($storeId)->getClientConfig()?->getClientName();

        if ($clientName) {
            AlgoliaAgent::addAlgoliaAgent($clientName, 'Magento2 integration', $this->config->getExtensionVersion());
            AlgoliaAgent::addAlgoliaAgent($clientName, 'PHP', phpversion());
            AlgoliaAgent::addAlgoliaAgent($clientName, 'Magento', $this->config->getMagentoVersion());
            AlgoliaAgent::addAlgoliaAgent($clientName, 'Edition', $this->config->getMagentoEdition());

            $this->userAgentsAdded = true;
        }
    }

    protected function getConnectionTimeout(int $storeId): int
    {
        return $this->config->getConnectionTimeout($storeId);
    }

    protected function getReadTimeout(int $storeId): int
    {
        return $this->config->getReadTimeout($storeId);
    }
}
