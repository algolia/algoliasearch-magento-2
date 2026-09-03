<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\SearchClientProvider;
use Algolia\AlgoliaSearch\Test\TestCase;

class SearchClientProviderTest extends TestCase
{
    protected function createObjectToTest(
        ?ConfigHelper $config = null,
        ?AlgoliaCredentialsManager $credentialsManager = null,
    ): SearchClientProvider {
        $config ??= $this->createStub(ConfigHelper::class);
        $config->method('getExtensionVersion')->willReturn('3.19.0');
        $config->method('getMagentoVersion')->willReturn('2.4.8');
        $config->method('getMagentoEdition')->willReturn('Community');

        return new SearchClientProvider(
            $config,
            $credentialsManager ?? $this->createStub(AlgoliaCredentialsManager::class),
        );
    }

    public function testGetClientThrowsWhenCredentialsInvalid(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->expects($this->once())->method('checkCredentials')->willReturn(false);

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

    public function testGetClientCachesPerStore(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->expects($this->once())
            ->method('checkCredentials')
            ->with(1)
            ->willReturn(true);

        $config = $this->createStub(ConfigHelper::class);
        $config->method('getApplicationID')->willReturn('test-app-id');
        $config->method('getAPIKey')->willReturn('test-api-key');
        $config->method('getConnectionTimeout')->willReturn(5);
        $config->method('getReadTimeout')->willReturn(10);
        $config->method('getWriteTimeout')->willReturn(30);

        $provider = $this->createObjectToTest($config, $credentialsManager);

        $client1 = $provider->getClient(1);
        $client1Again = $provider->getClient(1);

        $this->assertSame($client1, $client1Again);
    }

    public function testGetClientReturnsDifferentClientsPerStore(): void
    {
        $storeIds = [];
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->expects($this->exactly(2))
            ->method('checkCredentials')
            ->with($this->callback(function (int $storeId) use (&$storeIds) {
                $storeIds[] = $storeId;
                return true;
            }))
            ->willReturn(true);

        $config = $this->createStub(ConfigHelper::class);
        $config->method('getApplicationID')->willReturn('test-app-id');
        $config->method('getAPIKey')->willReturn('test-api-key');
        $config->method('getConnectionTimeout')->willReturn(5);
        $config->method('getReadTimeout')->willReturn(10);
        $config->method('getWriteTimeout')->willReturn(30);

        $provider = $this->createObjectToTest($config, $credentialsManager);

        $client1 = $provider->getClient(1);
        $client2 = $provider->getClient(2);

        $this->assertNotSame($client1, $client2);
        $this->assertEquals([1, 2], $storeIds);
    }
}
