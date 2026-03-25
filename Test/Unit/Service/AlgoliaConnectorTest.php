<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Output\ConsoleOutput;

class AlgoliaConnectorTest extends TestCase
{
    private ?AlgoliaConnector $connector = null;
    private null|(SearchClient&MockObject) $searchClient = null;
    private null|(ConfigHelper&MockObject) $config = null;
    private null|(IndexOptionsInterface&MockObject) $indexOptions = null;

    private const STORE_ID = 1;
    private const INDEX_NAME = 'magento2_default_products';
    private const TASK_ID = 12345;

    protected function setUp(): void
    {
        $this->searchClient = $this->createMock(SearchClient::class);
        $this->config = $this->createMock(ConfigHelper::class);

        $this->config->method('getNonCastableAttributes')->willReturn([]);
        $this->config->method('getMaxRecordSizeLimit')->willReturn(10000);

        $this->connector = new AlgoliaConnector(
            $this->config,
            $this->createMock(ManagerInterface::class),
            $this->createMock(ConsoleOutput::class),
            $this->createMock(AlgoliaCredentialsManager::class),
            $this->createMock(IndexNameFetcher::class),
            $this->createMock(IndexOptionsBuilder::class)
        );

        // Inject mock SearchClient directly into the clients array
        $this->setPrivateProperty($this->connector, 'clients', [self::STORE_ID => $this->searchClient]);

        $this->indexOptions = $this->createMock(IndexOptionsInterface::class);
        $this->indexOptions->method('getStoreId')->willReturn(self::STORE_ID);
        $this->indexOptions->method('getIndexName')->willReturn(self::INDEX_NAME);
    }

    // ── saveObjects() ──

    public function testSaveObjectsCallsBatchWithAddObjectAction(): void
    {
        $objects = [
            ['objectID' => '1', 'name' => 'Product A'],
            ['objectID' => '2', 'name' => 'Product B'],
        ];

        $this->searchClient->expects($this->once())
            ->method('batch')
            ->with(
                self::INDEX_NAME,
                $this->callback(function ($batchParams) {
                    $requests = $batchParams['requests'];
                    $this->assertCount(2, $requests);
                    $this->assertEquals('addObject', $requests[0]['action']);
                    $this->assertEquals('addObject', $requests[1]['action']);
                    $this->assertEquals('1', $requests[0]['body']['objectID']);
                    $this->assertEquals('2', $requests[1]['body']['objectID']);
                    return true;
                })
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->saveObjects($this->indexOptions, $objects);
    }

    public function testSaveObjectsWithPartialUpdateCallsBatchWithPartialUpdateAction(): void
    {
        $objects = [
            ['objectID' => '1', 'price' => '29.99'],
        ];

        $this->searchClient->expects($this->once())
            ->method('batch')
            ->with(
                self::INDEX_NAME,
                $this->callback(function ($batchParams) {
                    $this->assertEquals('partialUpdateObject', $batchParams['requests'][0]['action']);
                    return true;
                })
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->saveObjects($this->indexOptions, $objects, true);
    }

    public function testSaveObjectsTracksLastOperationInfo(): void
    {
        $objects = [['objectID' => '1', 'name' => 'Product A']];

        $this->searchClient->method('batch')
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->saveObjects($this->indexOptions, $objects);

        $this->assertEquals(self::TASK_ID, $this->connector->getLastTaskId(self::STORE_ID));
    }


    public function testSaveObjectsSetsAlgoliaLastUpdateTimestamp(): void
    {
        $objects = [['objectID' => '1', 'name' => 'Product A']];
        $beforeTime = strtotime('now');

        $this->searchClient->expects($this->once())
            ->method('batch')
            ->with(
                self::INDEX_NAME,
                $this->callback(function ($batchParams) use ($beforeTime) {
                    $body = $batchParams['requests'][0]['body'];
                    $this->assertArrayHasKey('algoliaLastUpdateAtCET', $body);
                    $this->assertGreaterThanOrEqual($beforeTime, $body['algoliaLastUpdateAtCET']);
                    return true;
                })
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->saveObjects($this->indexOptions, $objects);
    }

    public function testSaveObjectsCastsNumericValues(): void
    {
        $objects = [['objectID' => '1', 'price' => '29.99', 'qty' => '5']];

        $this->searchClient->expects($this->once())
            ->method('batch')
            ->with(
                self::INDEX_NAME,
                $this->callback(function ($batchParams) {
                    $body = $batchParams['requests'][0]['body'];
                    $this->assertSame(29.99, $body['price']);
                    $this->assertSame(5, $body['qty']);
                    return true;
                })
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->saveObjects($this->indexOptions, $objects);
    }

    public function testSaveObjectsSkipsOversizedRecords(): void
    {
        // Set max size large enough for small records but too small for the bloated one
        $this->setPrivateProperty($this->connector, 'maxRecordSize', 200);

        $objects = [
            ['objectID' => '1', 'name' => 'Small'],
            ['objectID' => '2', 'name' => str_repeat('x', 10000)],
        ];

        $this->searchClient->expects($this->once())
            ->method('batch')
            ->with(
                self::INDEX_NAME,
                $this->callback(function ($batchParams) {
                    $requests = $batchParams['requests'];
                    // Only the small record should survive
                    $this->assertCount(1, $requests);
                    $this->assertEquals('1', $requests[0]['body']['objectID']);
                    return true;
                })
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->saveObjects($this->indexOptions, $objects);
    }

    // ── deleteObjects() ──

    public function testDeleteObjectsCallsBatchWithDeleteObjectAction(): void
    {
        $ids = ['100', '200', '300'];

        $this->searchClient->expects($this->once())
            ->method('batch')
            ->with(
                self::INDEX_NAME,
                $this->callback(function ($batchParams) {
                    $requests = $batchParams['requests'];
                    $this->assertCount(3, $requests);
                    foreach ($requests as $request) {
                        $this->assertEquals('deleteObject', $request['action']);
                    }
                    $this->assertEquals('100', $requests[0]['body']['objectID']);
                    $this->assertEquals('200', $requests[1]['body']['objectID']);
                    $this->assertEquals('300', $requests[2]['body']['objectID']);
                    return true;
                })
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->deleteObjects($ids, $this->indexOptions);
    }

    public function testDeleteObjectsTracksLastOperationInfo(): void
    {
        $ids = ['100'];

        $this->searchClient->method('batch')
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->deleteObjects($ids, $this->indexOptions);

        $this->assertEquals(self::TASK_ID, $this->connector->getLastTaskId(self::STORE_ID));
    }

    // ── performBatchOperation()  ──

    public function testPerformBatchOperationDelegatesToClientBatch(): void
    {
        $requests = [
            ['action' => 'addObject', 'body' => ['objectID' => '1', 'name' => 'Test']],
        ];

        $this->searchClient->expects($this->once())
            ->method('batch')
            ->with(self::INDEX_NAME, ['requests' => $requests])
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->invokeMethod($this->connector, 'performBatchOperation', [$this->indexOptions, $requests]);
    }

    public function testPerformBatchOperationReturnsClientResponse(): void
    {
        $expectedResponse = [
            AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID,
            'objectIDs' => ['1', '2'],
        ];

        $this->searchClient->method('batch')->willReturn($expectedResponse);

        $result = $this->invokeMethod($this->connector, 'performBatchOperation', [
            $this->indexOptions,
            [['action' => 'addObject', 'body' => ['objectID' => '1']]],
        ]);

        $this->assertEquals($expectedResponse, $result);
    }
}
