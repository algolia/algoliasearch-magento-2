<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Api\SearchClientProviderInterface;
use Algolia\AlgoliaSearch\Api\SendStrategyInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\SendStrategyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Output\ConsoleOutput;

class AlgoliaConnectorTest extends TestCase
{
    private ?AlgoliaConnector $connector = null;
    private null|(SendStrategyInterface&MockObject) $mockStrategy = null;
    private null|(ConfigHelper&MockObject) $config = null;
    private null|(IndexOptionsInterface&MockObject) $indexOptions = null;
    private null|(SearchClientProviderInterface&MockObject) $clientProvider = null;
    private null|(SearchClient&MockObject) $client = null;

    private const STORE_ID = 1;
    private const INDEX_NAME = 'magento2_default_products';
    private const TASK_ID = 12345;

    protected function setUp(): void
    {
        $this->mockStrategy = $this->createMock(SendStrategyInterface::class);
        $mockResolver = $this->createMock(SendStrategyResolver::class);
        $mockResolver->method('resolve')->willReturn($this->mockStrategy);

        $this->config = $this->createMock(ConfigHelper::class);
        $this->config->method('getNonCastableAttributes')->willReturn([]);
        $this->config->method('getMaxRecordSizeLimit')->willReturn(10000);

        $this->client = $this->createMock(SearchClient::class);
        $this->clientProvider = $this->createMock(SearchClientProviderInterface::class);
        $this->clientProvider->method('getClient')->willReturn($this->client);

        $this->connector = new AlgoliaConnector(
            $this->config,
            $this->createMock(ManagerInterface::class),
            $this->createMock(ConsoleOutput::class),
            $this->clientProvider,
            $this->createMock(IndexNameFetcher::class),
            $this->createMock(IndexOptionsBuilder::class),
            $mockResolver
        );

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

        $this->mockStrategy->expects($this->once())
            ->method('send')
            ->with(
                $this->indexOptions,
                $this->callback(function ($requests) {
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

        $this->mockStrategy->expects($this->once())
            ->method('send')
            ->with(
                $this->indexOptions,
                $this->callback(function ($requests) {
                    $this->assertEquals('partialUpdateObject', $requests[0]['action']);

                    return true;
                })
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->saveObjects($this->indexOptions, $objects, true);
    }

    public function testSaveObjectsTracksLastOperationInfo(): void
    {
        $objects = [['objectID' => '1', 'name' => 'Product A']];

        $this->mockStrategy->method('send')
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->saveObjects($this->indexOptions, $objects);

        $this->assertEquals(self::TASK_ID, $this->connector->getLastTaskId(self::STORE_ID));
    }

    public function testSaveObjectsSetsAlgoliaLastUpdateTimestamp(): void
    {
        $objects = [['objectID' => '1', 'name' => 'Product A']];
        $beforeTime = strtotime('now');

        $this->mockStrategy->expects($this->once())
            ->method('send')
            ->with(
                $this->indexOptions,
                $this->callback(function ($requests) use ($beforeTime) {
                    $body = $requests[0]['body'];
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

        $this->mockStrategy->expects($this->once())
            ->method('send')
            ->with(
                $this->indexOptions,
                $this->callback(function ($requests) {
                    $body = $requests[0]['body'];
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

        $this->mockStrategy->expects($this->once())
            ->method('send')
            ->with(
                $this->indexOptions,
                $this->callback(function ($requests) {
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

        $this->mockStrategy->expects($this->once())
            ->method('send')
            ->with(
                $this->indexOptions,
                $this->callback(function ($requests) {
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

        $this->mockStrategy->method('send')
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->deleteObjects($ids, $this->indexOptions);

        $this->assertEquals(self::TASK_ID, $this->connector->getLastTaskId(self::STORE_ID));
    }

    // ── performBatchOperation() ──

    public function testPerformBatchOperationDelegatesToStrategy(): void
    {
        $requests = [
            ['action' => 'addObject', 'body' => ['objectID' => '1', 'name' => 'Test']],
        ];

        $this->mockStrategy->expects($this->once())
            ->method('send')
            ->with($this->indexOptions, $requests)
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->invokeMethod($this->connector, 'performBatchOperation', [$this->indexOptions, $requests]);
    }

    public function testPerformBatchOperationReturnsStrategyResponse(): void
    {
        $expectedResponse = [
            AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID,
            'objectIDs' => ['1', '2'],
        ];

        $this->mockStrategy->method('send')->willReturn($expectedResponse);

        $result = $this->invokeMethod($this->connector, 'performBatchOperation', [
            $this->indexOptions,
            [['action' => 'addObject', 'body' => ['objectID' => '1']]],
        ]);

        $this->assertEquals($expectedResponse, $result);
    }

    // ── getSettings() ──

    public function testGetSettingsReturnsEmptyArrayWhenIndexDoesNotExist(): void
    {
        $this->client->method('getSettings')
            ->willThrowException(new \Exception('Not Found', 404));

        $this->assertSame([], $this->connector->getSettings($this->indexOptions));
    }

    public function testGetSettingsRethrowsNon404Exceptions(): void
    {
        $this->client->method('getSettings')
            ->willThrowException(new \Exception('Internal Server Error', 500));

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(500);

        $this->connector->getSettings($this->indexOptions);
    }

    // ── setSettings() ──

    public function testSetSettingsForwardsSettingsToClient(): void
    {
        $settings = ['searchableAttributes' => ['name', 'description']];

        $this->client->expects($this->once())
            ->method('setSettings')
            ->with(self::INDEX_NAME, $settings, false)
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->setSettings($this->indexOptions, $settings);
    }

    // ── deleteIndex() ──

    public function testDeleteIndexDelegatesToClient(): void
    {
        $this->client->expects($this->once())
            ->method('deleteIndex')
            ->with(self::INDEX_NAME)
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->deleteIndex($this->indexOptions);
    }

    // ── moveIndex() ──

    public function testMoveIndexCallsOperationIndexWithMoveOperation(): void
    {
        $toIndexOptions = $this->createMock(IndexOptionsInterface::class);
        $toIndexOptions->method('getIndexName')->willReturn('magento2_default_products_tmp');
        $toIndexOptions->method('getStoreId')->willReturn(self::STORE_ID);

        $this->client->expects($this->once())
            ->method('operationIndex')
            ->with(self::INDEX_NAME, [
                'operation'   => 'move',
                'destination' => 'magento2_default_products_tmp',
            ])
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->moveIndex($this->indexOptions, $toIndexOptions);
    }

    // ── clearIndex() ──

    public function testClearIndexCallsClearObjectsOnClient(): void
    {
        $this->client->expects($this->once())
            ->method('clearObjects')
            ->with(self::INDEX_NAME)
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->clearIndex($this->indexOptions);
    }

    // ── copySynonyms() / copyQueryRules() ──

    public function testCopySynonymsUsesOperationIndexWithSynonymsScope(): void
    {
        $toIndexOptions = $this->createMock(IndexOptionsInterface::class);
        $toIndexOptions->method('getIndexName')->willReturn('magento2_default_products_replica');
        $toIndexOptions->method('getStoreId')->willReturn(self::STORE_ID);

        $this->client->expects($this->once())
            ->method('operationIndex')
            ->with(self::INDEX_NAME, [
                'operation'   => 'copy',
                'destination' => 'magento2_default_products_replica',
                'scope'       => ['synonyms'],
            ])
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->copySynonyms($this->indexOptions, $toIndexOptions);
    }

    public function testCopyQueryRulesUsesOperationIndexWithRulesScope(): void
    {
        $toIndexOptions = $this->createMock(IndexOptionsInterface::class);
        $toIndexOptions->method('getIndexName')->willReturn('magento2_default_products_replica');
        $toIndexOptions->method('getStoreId')->willReturn(self::STORE_ID);

        $this->client->expects($this->once())
            ->method('operationIndex')
            ->with(self::INDEX_NAME, [
                'operation'   => 'copy',
                'destination' => 'magento2_default_products_replica',
                'scope'       => ['rules'],
            ])
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->copyQueryRules($this->indexOptions, $toIndexOptions);
    }

    // ── saveRule() / deleteRule() ──

    public function testSaveRuleDelegatesToClientWithCorrectArguments(): void
    {
        $rule = [AlgoliaConnector::ALGOLIA_API_OBJECT_ID => 'rule-1', 'condition' => []];

        $this->client->expects($this->once())
            ->method('saveRule')
            ->with(self::INDEX_NAME, 'rule-1', $rule, false)
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->saveRule($rule, $this->indexOptions);
    }

    public function testDeleteRuleDelegatesToClientWithCorrectArguments(): void
    {
        $this->client->expects($this->once())
            ->method('deleteRule')
            ->with(self::INDEX_NAME, 'rule-1', false)
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $this->connector->deleteRule($this->indexOptions, 'rule-1');
    }
}
