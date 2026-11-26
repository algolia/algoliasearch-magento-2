<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\CompositionClient;
use Algolia\AlgoliaSearch\Api\CompositionClientProviderInterface;
use Algolia\AlgoliaSearch\Configuration\CompositionConfig;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;

class CompositionClientProvider extends AbstractClientProvider implements CompositionClientProviderInterface
{
    /** @var CompositionClient[] */
    protected array $clients = [];

    /**
     * @throws AlgoliaException
     */
    public function getClient(?int $storeId = self::ALGOLIA_DEFAULT_SCOPE): CompositionClient
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

        $config = CompositionConfig::create(
            $this->config->getApplicationID($storeId),
            $this->config->getAPIKey($storeId)
        );
        $config->setConnectTimeout($this->getConnectionTimeout($storeId));
        $config->setReadTimeout($this->getReadTimeout($storeId));
        $config->setWriteTimeout($this->config->getWriteTimeout($storeId));

        $this->clients[$storeId] = CompositionClient::createWithConfig($config);
    }
}
