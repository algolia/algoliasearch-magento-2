<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Api\SearchClientProviderInterface;
use Algolia\AlgoliaSearch\Configuration\SearchConfig;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;

class SearchClientProvider extends AbstractClientProvider implements SearchClientProviderInterface
{
    /** @var SearchClient[] */
    protected array $clients = [];

    public function __construct(
        protected ConfigHelper $config,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    ) {}

    /**
     * @throws AlgoliaException
     */
    public function getClient(?int $storeId = self::ALGOLIA_DEFAULT_SCOPE): SearchClient
    {
        $this->createClientByStore($storeId);

        return $this->clients[$storeId ?: self::ALGOLIA_DEFAULT_SCOPE];
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
}
