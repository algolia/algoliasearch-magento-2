<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Api\SearchClientProviderInterface;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\DirectSendStrategy;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DirectSendStrategyTest extends TestCase
{
    private null|(SearchClient&MockObject) $searchClient = null;
    private null|(SearchClientProviderInterface&MockObject) $clientProvider = null;
    private ?DirectSendStrategy $strategy = null;
    private null|(IndexOptionsInterface&MockObject) $indexOptions = null;

    private const STORE_ID = 1;
    private const INDEX_NAME = 'magento2_default_products';
    private const TASK_ID = 12345;

    protected function setUp(): void
    {
        $this->searchClient = $this->createMock(SearchClient::class);
        $this->clientProvider = $this->createMock(SearchClientProviderInterface::class);
        $this->clientProvider->method('getClient')->willReturn($this->searchClient);

        $this->strategy = new DirectSendStrategy($this->clientProvider);

        $this->indexOptions = $this->createMock(IndexOptionsInterface::class);
        $this->indexOptions->method('getStoreId')->willReturn(self::STORE_ID);
        $this->indexOptions->method('getIndexName')->willReturn(self::INDEX_NAME);
    }

    public function testIsApplicableAlwaysReturnsTrue(): void
    {
        $this->assertTrue($this->strategy->isApplicable(self::STORE_ID));
        $this->assertTrue($this->strategy->isApplicable(0));
        $this->assertTrue($this->strategy->isApplicable(99));
    }

    public function testSendCallsClientBatchWithCorrectIndexNameAndRequests(): void
    {
        $requests = [
            ['action' => 'addObject', 'body' => ['objectID' => '1', 'name' => 'Product A']],
            ['action' => 'addObject', 'body' => ['objectID' => '2', 'name' => 'Product B']],
        ];

        $this->clientProvider->expects($this->once())
            ->method('getClient')
            ->with(self::STORE_ID)
            ->willReturn($this->searchClient);

        $this->searchClient->expects($this->once())
            ->method('batch')
            ->with(self::INDEX_NAME, ['requests' => $requests])
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->strategy->send($this->indexOptions, $requests);
    }

    public function testSendReturnsClientResponse(): void
    {
        $expectedResponse = [
            AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID,
            'objectIDs' => ['1', '2'],
        ];

        $this->searchClient->method('batch')->willReturn($expectedResponse);

        $result = $this->strategy->send($this->indexOptions, []);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testSendWithDeleteObjectAction(): void
    {
        $requests = [
            ['action' => 'deleteObject', 'body' => ['objectID' => '100']],
        ];

        $this->searchClient->expects($this->once())
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

        $this->strategy->send($this->indexOptions, $requests);
    }
}
