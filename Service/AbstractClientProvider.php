<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\ClientProviderInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Support\AlgoliaAgent;

abstract class AbstractClientProvider
{
    protected bool $userAgentsAdded = false;

    /**
     * @throws AlgoliaException
     */
    protected function addAlgoliaUserAgent(int $storeId = ClientProviderInterface::ALGOLIA_DEFAULT_SCOPE): void
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

    /**
     * @throws AlgoliaException
     */
    protected function createClientByStore(?int $storeId): void
    {
        if ($storeId === null) {
            $storeId = ClientProviderInterface::ALGOLIA_DEFAULT_SCOPE;
        }

        if (!isset($this->clients[$storeId])) {
            $this->createClient($storeId);
            if (!$this->userAgentsAdded) {
                $this->addAlgoliaUserAgent($storeId);
            }
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
