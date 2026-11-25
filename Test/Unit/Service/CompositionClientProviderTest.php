<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\CompositionClientProvider;
use Algolia\AlgoliaSearch\Test\TestCase;

class CompositionClientProviderTest extends TestCase
{
    protected function createObjectToTest(
        ?ConfigHelper $config = null,
        ?AlgoliaCredentialsManager $credentialsManager = null,
    ): CompositionClientProvider {
        $config ??= $this->createConfigStub();

        return new CompositionClientProvider(
            $config,
            $credentialsManager ?? $this->createStub(AlgoliaCredentialsManager::class),
        );
    }

    protected function createConfigStub(): ConfigHelper
    {
        $config = $this->createStub(ConfigHelper::class);
        $config->method('getApplicationID')->willReturn('test-app-id');
        $config->method('getAPIKey')->willReturn('test-api-key');
        $config->method('getConnectionTimeout')->willReturn(5);
        $config->method('getReadTimeout')->willReturn(10);
        $config->method('getWriteTimeout')->willReturn(30);
        $config->method('getExtensionVersion')->willReturn('3.20.0');
        $config->method('getMagentoVersion')->willReturn('2.4.9');
        $config->method('getMagentoEdition')->willReturn('Community');

        return $config;
    }

    public function testGetClientThrowsWhenCredentialsInvalid(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->expects($this->once())->method('checkCredentials')->with(1)->willReturn(false);

        $provider = $this->createObjectToTest(credentialsManager: $credentialsManager);

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Algolia credentials were not provided');

        $provider->getClient(1);
    }

    public function testGetClientWithNullStoreIdDefaultsToZero(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->expects($this->once())
            ->method('checkCredentials')
            ->with(0)
            ->willReturn(false);

        $provider = $this->createObjectToTest(credentialsManager: $credentialsManager);

        $this->expectException(AlgoliaException::class);

        $provider->getClient(null);
    }

    public function testGetClientAppliesConfiguredTimeouts(): void
    {
        $credentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentials')->willReturn(true);

        $provider = $this->createObjectToTest(credentialsManager: $credentialsManager);

        $clientConfig = $provider->getClient(1)->getClientConfig();

        $this->assertSame(5, $clientConfig->getConnectTimeout());
        $this->assertSame(10, $clientConfig->getReadTimeout());
        $this->assertSame(30, $clientConfig->getWriteTimeout());
    }

    public function testGetClientCachesPerStore(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->expects($this->once())
            ->method('checkCredentials')
            ->with(1)
            ->willReturn(true);

        $provider = $this->createObjectToTest(credentialsManager: $credentialsManager);

        $client = $provider->getClient(1);

        $this->assertSame($client, $provider->getClient(1));
    }

    public function testGetClientReturnsDifferentClientsPerStore(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->expects($this->exactly(2))
            ->method('checkCredentials')
            ->willReturn(true);

        $provider = $this->createObjectToTest(credentialsManager: $credentialsManager);

        $this->assertNotSame($provider->getClient(1), $provider->getClient(2));
    }
}
