<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\SearchClientProvider;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SearchClientProviderTest extends TestCase
{
    private null|(ConfigHelper&MockObject) $config = null;
    private null|(AlgoliaCredentialsManager&MockObject) $credentialsManager = null;
    private ?SearchClientProvider $provider = null;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigHelper::class);
        $this->config->method('getExtensionVersion')->willReturn('3.19.0');
        $this->config->method('getMagentoVersion')->willReturn('2.4.8');
        $this->config->method('getMagentoEdition')->willReturn('Community');

        $this->credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);

        $this->provider = new SearchClientProvider(
            $this->config,
            $this->credentialsManager
        );
    }

    public function testGetClientThrowsWhenCredentialsInvalid(): void
    {
        $this->credentialsManager->method('checkCredentials')->willReturn(false);

        $this->expectException(AlgoliaException::class);
        $this->expectExceptionMessage('Algolia credentials were not provided');

        $this->provider->getClient(1);
    }

    public function testGetClientWithNullStoreIdDefaultsToZero(): void
    {
        $this->credentialsManager->method('checkCredentials')
            ->with(0)
            ->willReturn(false);

        $this->expectException(AlgoliaException::class);

        $this->provider->getClient(null);
    }

    public function testGetClientCachesPerStore(): void
    {
        $this->credentialsManager->expects($this->once())
            ->method('checkCredentials')
            ->with(1)
            ->willReturn(true);
        $this->config->method('getApplicationID')->willReturn('test-app-id');
        $this->config->method('getAPIKey')->willReturn('test-api-key');
        $this->config->method('getConnectionTimeout')->willReturn(5);
        $this->config->method('getReadTimeout')->willReturn(10);
        $this->config->method('getWriteTimeout')->willReturn(30);

        $client1 = $this->provider->getClient(1);
        $client1Again = $this->provider->getClient(1);

        $this->assertSame($client1, $client1Again);
    }

    public function testGetClientReturnsDifferentClientsPerStore(): void
    {
        $storeIds = [];
        $this->credentialsManager->expects($this->exactly(2))
            ->method('checkCredentials')
            ->with($this->callback(function (int $storeId) use (&$storeIds) {
                $storeIds[] = $storeId;
                return true;
            }))
            ->willReturn(true);
        $this->config->method('getApplicationID')->willReturn('test-app-id');
        $this->config->method('getAPIKey')->willReturn('test-api-key');
        $this->config->method('getConnectionTimeout')->willReturn(5);
        $this->config->method('getReadTimeout')->willReturn(10);
        $this->config->method('getWriteTimeout')->willReturn(30);

        $client1 = $this->provider->getClient(1);
        $client2 = $this->provider->getClient(2);

        $this->assertNotSame($client1, $client2);
        $this->assertEquals([1, 2], $storeIds);
    }
}
