<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Api\SearchClientProviderInterface;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\DirectSendStrategy;
use Algolia\AlgoliaSearch\Test\TestCase;

class DirectSendStrategyTest extends TestCase
{
    private const STORE_ID = 1;
    private const INDEX_NAME = 'magento2_default_products';
    private const TASK_ID = 12345;

    protected function createObjectToTest(?SearchClientProviderInterface $clientProvider = null): DirectSendStrategy
    {
        return new DirectSendStrategy($clientProvider ?? $this->createStub(SearchClientProviderInterface::class));
    }

    private function createIndexOptionsStub(): IndexOptionsInterface
    {
        $indexOptions = $this->createStub(IndexOptionsInterface::class);
        $indexOptions->method('getStoreId')->willReturn(self::STORE_ID);
        $indexOptions->method('getIndexName')->willReturn(self::INDEX_NAME);

        return $indexOptions;
    }

    public function testIsApplicableAlwaysReturnsTrue(): void
    {
        $strategy = $this->createObjectToTest();

        $this->assertTrue($strategy->isApplicable(self::STORE_ID));
        $this->assertTrue($strategy->isApplicable(0));
        $this->assertTrue($strategy->isApplicable(99));
    }

    public function testSendCallsClientBatchWithCorrectIndexNameAndRequests(): void
    {
        $requests = [
            ['action' => 'addObject', 'body' => ['objectID' => '1', 'name' => 'Product A']],
            ['action' => 'addObject', 'body' => ['objectID' => '2', 'name' => 'Product B']],
        ];

        $searchClient = $this->createMock(SearchClient::class);
        $searchClient->expects($this->once())
            ->method('batch')
            ->with(self::INDEX_NAME, ['requests' => $requests])
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $clientProvider = $this->createMock(SearchClientProviderInterface::class);
        $clientProvider->expects($this->once())
            ->method('getClient')
            ->with(self::STORE_ID)
            ->willReturn($searchClient);

        $strategy = $this->createObjectToTest($clientProvider);

        $strategy->send($this->createIndexOptionsStub(), $requests);
    }

    public function testSendReturnsClientResponse(): void
    {
        $expectedResponse = [
            AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID,
            'objectIDs' => ['1', '2'],
        ];

        $searchClient = $this->createStub(SearchClient::class);
        $searchClient->method('batch')->willReturn($expectedResponse);

        $clientProvider = $this->createStub(SearchClientProviderInterface::class);
        $clientProvider->method('getClient')->willReturn($searchClient);

        $strategy = $this->createObjectToTest($clientProvider);

        $result = $strategy->send($this->createIndexOptionsStub(), []);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testSendWithDeleteObjectAction(): void
    {
        $requests = [
            ['action' => 'deleteObject', 'body' => ['objectID' => '100']],
        ];

        $searchClient = $this->createMock(SearchClient::class);
        $searchClient->expects($this->once())
            ->method('batch')
            ->with(
                self::INDEX_NAME,
                $this->callback(function ($batchParams) {
                    $this->assertEquals('deleteObject', $batchParams['requests'][0]['action']);
                    $this->assertEquals('100', $batchParams['requests'][0]['body']['objectID']);

                    return true;
                })
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $clientProvider = $this->createStub(SearchClientProviderInterface::class);
        $clientProvider->method('getClient')->willReturn($searchClient);

        $strategy = $this->createObjectToTest($clientProvider);

        $strategy->send($this->createIndexOptionsStub(), $requests);
    }
}
