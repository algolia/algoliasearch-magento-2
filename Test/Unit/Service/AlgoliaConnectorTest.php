<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterface;
use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Api\SearchClientProviderInterface;
use Algolia\AlgoliaSearch\Api\SendStrategyInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Index\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\Index\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\SendStrategyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Message\ManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class AlgoliaConnectorTest extends TestCase
{
    private const STORE_ID = 1;
    private const INDEX_NAME = 'magento2_default_products';
    private const TASK_ID = 12345;

    protected function createObjectToTest(
        ?ConfigHelper $config = null,
        ?SendStrategyInterface $strategy = null,
        ?SearchClient $client = null,
    ): AlgoliaConnector {
        $config ??= $this->createStub(ConfigHelper::class);
        $config->method('getNonCastableAttributes')->willReturn([]);
        $config->method('getMaxRecordSizeLimit')->willReturn(10000);

        $resolver = $this->createStub(SendStrategyResolver::class);
        $resolver->method('resolve')->willReturn($strategy ?? $this->createStub(SendStrategyInterface::class));

        $clientProvider = $this->createStub(SearchClientProviderInterface::class);
        $clientProvider->method('getClient')->willReturn($client ?? $this->createStub(SearchClient::class));

        return new AlgoliaConnector(
            $config,
            $this->createStub(ManagerInterface::class),
            $this->createStub(ConsoleOutput::class),
            $clientProvider,
            $this->createStub(IndexNameFetcher::class),
            $this->createStub(IndexOptionsBuilder::class),
            $resolver,
        );
    }

    private function createIndexOptionsStub(string $indexName = self::INDEX_NAME, int $storeId = self::STORE_ID): IndexOptionsInterface
    {
        $indexOptions = $this->createStub(IndexOptionsInterface::class);
        $indexOptions->method('getStoreId')->willReturn($storeId);
        $indexOptions->method('getIndexName')->willReturn($indexName);

        return $indexOptions;
    }

    // ── saveObjects() ──

    public function testSaveObjectsCallsBatchWithAddObjectAction(): void
    {
        $objects = [
            ['objectID' => '1', 'name' => 'Product A'],
            ['objectID' => '2', 'name' => 'Product B'],
        ];

        $strategy = $this->createMock(SendStrategyInterface::class);
        $indexOptions = $this->createIndexOptionsStub();
        $strategy->expects($this->once())
            ->method('send')
            ->with(
                $indexOptions,
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

        $connector = $this->createObjectToTest(strategy: $strategy);

        $connector->saveObjects($indexOptions, $objects);
    }

    public function testSaveObjectsWithPartialUpdateCallsBatchWithPartialUpdateAction(): void
    {
        $objects = [
            ['objectID' => '1', 'price' => '29.99'],
        ];

        $strategy = $this->createMock(SendStrategyInterface::class);
        $indexOptions = $this->createIndexOptionsStub();
        $strategy->expects($this->once())
            ->method('send')
            ->with(
                $indexOptions,
                $this->callback(function ($requests) {
                    $this->assertEquals('partialUpdateObject', $requests[0]['action']);

                    return true;
                })
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(strategy: $strategy);

        $connector->saveObjects($indexOptions, $objects, true);
    }

    public function testSaveObjectsTracksLastOperationInfo(): void
    {
        $objects = [['objectID' => '1', 'name' => 'Product A']];

        $strategy = $this->createMock(SendStrategyInterface::class);
        // saveObjects() sends exactly one batch for a single non-oversized object.
        $strategy->expects($this->once())
            ->method('send')
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(strategy: $strategy);
        $indexOptions = $this->createIndexOptionsStub();

        $connector->saveObjects($indexOptions, $objects);

        $this->assertEquals(self::TASK_ID, $connector->getLastTaskId(self::STORE_ID));
    }

    public function testSaveObjectsSetsAlgoliaLastUpdateTimestamp(): void
    {
        $objects = [['objectID' => '1', 'name' => 'Product A']];
        $beforeTime = strtotime('now');

        $strategy = $this->createMock(SendStrategyInterface::class);
        $indexOptions = $this->createIndexOptionsStub();
        $strategy->expects($this->once())
            ->method('send')
            ->with(
                $indexOptions,
                $this->callback(function ($requests) use ($beforeTime) {
                    $body = $requests[0]['body'];
                    $this->assertArrayHasKey('algoliaLastUpdateAtCET', $body);
                    $this->assertGreaterThanOrEqual($beforeTime, $body['algoliaLastUpdateAtCET']);

                    return true;
                })
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(strategy: $strategy);

        $connector->saveObjects($indexOptions, $objects);
    }

    public function testSaveObjectsCastsNumericValues(): void
    {
        $objects = [['objectID' => '1', 'price' => '29.99', 'qty' => '5']];

        $strategy = $this->createMock(SendStrategyInterface::class);
        $indexOptions = $this->createIndexOptionsStub();
        $strategy->expects($this->once())
            ->method('send')
            ->with(
                $indexOptions,
                $this->callback(function ($requests) {
                    $body = $requests[0]['body'];
                    $this->assertSame(29.99, $body['price']);
                    $this->assertSame(5, $body['qty']);

                    return true;
                })
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(strategy: $strategy);

        $connector->saveObjects($indexOptions, $objects);
    }

    public function testSaveObjectsSkipsOversizedRecords(): void
    {
        $objects = [
            ['objectID' => '1', 'name' => 'Small'],
            ['objectID' => '2', 'name' => str_repeat('x', 10000)],
        ];

        $strategy = $this->createMock(SendStrategyInterface::class);
        $indexOptions = $this->createIndexOptionsStub();
        $strategy->expects($this->once())
            ->method('send')
            ->with(
                $indexOptions,
                $this->callback(function ($requests) {
                    $this->assertCount(1, $requests);
                    $this->assertEquals('1', $requests[0]['body']['objectID']);

                    return true;
                })
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(strategy: $strategy);
        // Set max size large enough for small records but too small for the bloated one
        $this->setPrivateProperty($connector, 'maxRecordSize', 200);

        $connector->saveObjects($indexOptions, $objects);
    }

    // ── deleteObjects() ──

    public function testDeleteObjectsCallsBatchWithDeleteObjectAction(): void
    {
        $ids = ['100', '200', '300'];

        $strategy = $this->createMock(SendStrategyInterface::class);
        $indexOptions = $this->createIndexOptionsStub();
        $strategy->expects($this->once())
            ->method('send')
            ->with(
                $indexOptions,
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

        $connector = $this->createObjectToTest(strategy: $strategy);

        $connector->deleteObjects($ids, $indexOptions);
    }

    public function testDeleteObjectsTracksLastOperationInfo(): void
    {
        $ids = ['100'];

        $strategy = $this->createMock(SendStrategyInterface::class);
        // deleteObjects() sends exactly one batch for a single id.
        $strategy->expects($this->once())
            ->method('send')
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(strategy: $strategy);
        $indexOptions = $this->createIndexOptionsStub();

        $connector->deleteObjects($ids, $indexOptions);

        $this->assertEquals(self::TASK_ID, $connector->getLastTaskId(self::STORE_ID));
    }

    // ── performBatchOperation() ──

    public function testPerformBatchOperationDelegatesToStrategy(): void
    {
        $requests = [
            ['action' => 'addObject', 'body' => ['objectID' => '1', 'name' => 'Test']],
        ];

        $strategy = $this->createMock(SendStrategyInterface::class);
        $indexOptions = $this->createIndexOptionsStub();
        $strategy->expects($this->once())
            ->method('send')
            ->with($indexOptions, $requests)
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(strategy: $strategy);

        $this->invokeMethod($connector, 'performBatchOperation', [$indexOptions, $requests]);
    }

    public function testPerformBatchOperationReturnsStrategyResponse(): void
    {
        $expectedResponse = [
            AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID,
            'objectIDs' => ['1', '2'],
        ];

        $strategy = $this->createMock(SendStrategyInterface::class);
        // performBatchOperation() invoked directly, so send() is called exactly once.
        $strategy->expects($this->once())->method('send')->willReturn($expectedResponse);

        $connector = $this->createObjectToTest(strategy: $strategy);

        $result = $this->invokeMethod($connector, 'performBatchOperation', [
            $this->createIndexOptionsStub(),
            [['action' => 'addObject', 'body' => ['objectID' => '1']]],
        ]);

        $this->assertEquals($expectedResponse, $result);
    }

    // ── getSettings() ──

    public function testGetSettingsReturnsEmptyArrayWhenIndexDoesNotExist(): void
    {
        $client = $this->createMock(SearchClient::class);
        // getSettings() is unconditionally called exactly once.
        $client->expects($this->once())
            ->method('getSettings')
            ->willThrowException(new \Exception('Not Found', 404));

        $connector = $this->createObjectToTest(client: $client);

        $this->assertSame([], $connector->getSettings($this->createIndexOptionsStub()));
    }

    public function testGetSettingsRethrowsNon404Exceptions(): void
    {
        $client = $this->createMock(SearchClient::class);
        $client->expects($this->once())
            ->method('getSettings')
            ->willThrowException(new \Exception('Internal Server Error', 500));

        $connector = $this->createObjectToTest(client: $client);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(500);

        $connector->getSettings($this->createIndexOptionsStub());
    }

    // ── setSettings() ──

    public function testSetSettingsForwardsSettingsToClient(): void
    {
        $settings = ['searchableAttributes' => ['name', 'description']];

        $client = $this->createMock(SearchClient::class);
        $client->expects($this->once())
            ->method('setSettings')
            ->with(self::INDEX_NAME, $settings, false)
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(client: $client);

        $connector->setSettings($this->createIndexOptionsStub(), $settings);
    }

    // ── deleteIndex() ──

    public function testDeleteIndexDelegatesToClient(): void
    {
        $client = $this->createMock(SearchClient::class);
        $client->expects($this->once())
            ->method('deleteIndex')
            ->with(self::INDEX_NAME)
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(client: $client);

        $connector->deleteIndex($this->createIndexOptionsStub());
    }

    // ── moveIndex() ──

    public function testMoveIndexCallsOperationIndexWithMoveOperation(): void
    {
        $toIndexOptions = $this->createIndexOptionsStub('magento2_default_products_tmp');

        $client = $this->createMock(SearchClient::class);
        $client->expects($this->once())
            ->method('operationIndex')
            ->with(self::INDEX_NAME, [
                'operation'   => 'move',
                'destination' => 'magento2_default_products_tmp',
            ])
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(client: $client);

        $connector->moveIndex($this->createIndexOptionsStub(), $toIndexOptions);
    }

    // ── clearIndex() ──

    public function testClearIndexCallsClearObjectsOnClient(): void
    {
        $client = $this->createMock(SearchClient::class);
        $client->expects($this->once())
            ->method('clearObjects')
            ->with(self::INDEX_NAME)
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(client: $client);

        $connector->clearIndex($this->createIndexOptionsStub());
    }

    // ── copySynonyms() / copyQueryRules() ──

    public function testCopySynonymsUsesOperationIndexWithSynonymsScope(): void
    {
        $toIndexOptions = $this->createIndexOptionsStub('magento2_default_products_replica');

        $client = $this->createMock(SearchClient::class);
        $client->expects($this->once())
            ->method('operationIndex')
            ->with(self::INDEX_NAME, [
                'operation'   => 'copy',
                'destination' => 'magento2_default_products_replica',
                'scope'       => ['synonyms'],
            ])
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(client: $client);

        $connector->copySynonyms($this->createIndexOptionsStub(), $toIndexOptions);
    }

    public function testCopyQueryRulesUsesOperationIndexWithRulesScope(): void
    {
        $toIndexOptions = $this->createIndexOptionsStub('magento2_default_products_replica');

        $client = $this->createMock(SearchClient::class);
        $client->expects($this->once())
            ->method('operationIndex')
            ->with(self::INDEX_NAME, [
                'operation'   => 'copy',
                'destination' => 'magento2_default_products_replica',
                'scope'       => ['rules'],
            ])
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(client: $client);

        $connector->copyQueryRules($this->createIndexOptionsStub(), $toIndexOptions);
    }

    // ── saveRule() / deleteRule() ──

    public function testSaveRuleDelegatesToClientWithCorrectArguments(): void
    {
        $rule = [AlgoliaConnector::ALGOLIA_API_OBJECT_ID => 'rule-1', 'condition' => []];

        $client = $this->createMock(SearchClient::class);
        $client->expects($this->once())
            ->method('saveRule')
            ->with(self::INDEX_NAME, 'rule-1', $rule, false)
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(client: $client);

        $connector->saveRule($rule, $this->createIndexOptionsStub());
    }

    public function testDeleteRuleDelegatesToClientWithCorrectArguments(): void
    {
        $client = $this->createMock(SearchClient::class);
        $client->expects($this->once())
            ->method('deleteRule')
            ->with(self::INDEX_NAME, 'rule-1', false)
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(client: $client);

        $connector->deleteRule($this->createIndexOptionsStub(), 'rule-1');
    }

    // ── query() ──

    public function testQueryBuildsCorrectRequestStructureForClient(): void
    {
        $queryOptions = $this->createIndexOptionsStub();

        $searchQuery = $this->createStub(SearchQueryInterface::class);
        $searchQuery->method('getIndexOptions')->willReturn($queryOptions);
        $searchQuery->method('getQuery')->willReturn('blue shirt');
        $searchQuery->method('getParams')->willReturn(['hitsPerPage' => 10]);

        $client = $this->createMock(SearchClient::class);
        $client->expects($this->once())
            ->method('search')
            ->with($this->callback(function (array $payload) {
                $request = $payload['requests'][0];
                $this->assertSame(self::INDEX_NAME, $request[AlgoliaConnector::ALGOLIA_API_INDEX_NAME]);
                $this->assertSame('blue shirt', $request['query']);
                $this->assertSame(10, $request['hitsPerPage']);
                return true;
            }))
            ->willReturn(['hits' => []]);

        $connector = $this->createObjectToTest(client: $client);

        $connector->query($searchQuery);
    }

    // ── getObjects() ──

    public function testGetObjectsMapsObjectIdsToRequestFormatWithIndexName(): void
    {
        $client = $this->createMock(SearchClient::class);
        $client->expects($this->once())
            ->method('getObjects')
            ->with($this->callback(function (array $payload) {
                $this->assertCount(2, $payload['requests']);
                $this->assertSame(self::INDEX_NAME, $payload['requests'][0][AlgoliaConnector::ALGOLIA_API_INDEX_NAME]);
                $this->assertSame('42', $payload['requests'][0][AlgoliaConnector::ALGOLIA_API_OBJECT_ID]);
                $this->assertSame('99', $payload['requests'][1][AlgoliaConnector::ALGOLIA_API_OBJECT_ID]);
                return true;
            }))
            ->willReturn(['results' => []]);

        $connector = $this->createObjectToTest(client: $client);

        $connector->getObjects($this->createIndexOptionsStub(), ['42', '99']);
    }

    // ── setSettings() merge branch ──

    public function testSetSettingsMergesLocalSettingsOnTopOfOnlineSettings(): void
    {
        $onlineSettings = ['ranking' => ['typo', 'geo'], 'attributesToIndex' => ['old_name']];
        $localSettings  = ['searchableAttributes' => ['new_name']];

        $client = $this->createMock(SearchClient::class);
        $client->method('getSettings')->willReturn($onlineSettings);
        $client->expects($this->once())
            ->method('setSettings')
            ->with(
                self::INDEX_NAME,
                $this->callback(function (array $merged) {
                    // attributesToIndex renamed + local override applied
                    $this->assertSame(['new_name'], $merged['searchableAttributes']);
                    // online-only keys preserved
                    $this->assertArrayHasKey('ranking', $merged);
                    // original attributesToIndex key removed
                    $this->assertArrayNotHasKey('attributesToIndex', $merged);
                    return true;
                }),
                false
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(client: $client);

        $connector->setSettings($this->createIndexOptionsStub(), $localSettings, false, true);
    }

    /**
     * Online settings containing `semanticSearch` must not appear in
     * the payload passed to the client's setSettings() during the temp-index
     * merge. An empty `semanticSearch` object round-trips through PHP as an
     * empty array and re-encodes as a JSON array, which the API rejects.
     */
    public function testSetSettingsStripsSemanticSearchFromMergedOnlineSettings(): void
    {
        $onlineSettings = [
            'searchableAttributes' => ['name', 'description'],
            'customRanking'        => ['desc(popularity)'],
            'semanticSearch'       => [], // empty object as returned by getSettings; the offending value
        ];
        $localSettings = ['attributesToSnippet' => ['description:10']];

        $client = $this->createMock(SearchClient::class);
        // Merge source: the live production index.
        $client->method('getSettings')->with('magento2_default_products')->willReturn($onlineSettings);

        $client->expects($this->once())
            ->method('setSettings')
            ->with(
                self::INDEX_NAME,
                $this->callback(function (array $merged) {
                    $this->assertArrayNotHasKey(
                        'semanticSearch',
                        $merged,
                        'semanticSearch must be stripped before the temp-index settings write'
                    );
                    // Other online settings and the local override still pass through.
                    $this->assertArrayHasKey('searchableAttributes', $merged);
                    $this->assertArrayHasKey('customRanking', $merged);
                    $this->assertArrayHasKey('attributesToSnippet', $merged);

                    return true;
                }),
                false
            )
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(client: $client);

        $connector->setSettings(
            $this->createIndexOptionsStub(),
            $localSettings,
            false,
            true,
            'magento2_default_products'
        );
    }

    /**
     * Pins the strip list directly so the regression cannot be reintroduced by
     * an edit to getSettingsToRemove() that drops the semanticSearch entry.
     */
    public function testGetSettingsToRemoveIncludesSemanticSearch(): void
    {
        $connector = $this->createObjectToTest();

        $removals = $this->invokeMethod($connector, 'getSettingsToRemove', [[]]);

        $this->assertContains('semanticSearch', $removals);
    }

    // ── waitLastTask() ──

    public function testWaitLastTaskReturnsEarlyWhenNoOperationHasBeenPerformed(): void
    {
        $client = $this->createMock(SearchClient::class);
        $client->expects($this->never())->method('waitForTask');

        $connector = $this->createObjectToTest(client: $client);

        $connector->waitLastTask();
    }

    public function testWaitLastTaskCallsClientWithStoreSpecificStateAfterOperation(): void
    {
        $objects = [['objectID' => '1', 'name' => 'A']];

        $strategy = $this->createMock(SendStrategyInterface::class);
        // saveObjects() sends exactly one batch for a single object.
        $strategy->expects($this->once())
            ->method('send')
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $client = $this->createMock(SearchClient::class);
        $client->expects($this->once())
            ->method('waitForTask')
            ->with(self::INDEX_NAME, self::TASK_ID);

        $connector = $this->createObjectToTest(strategy: $strategy, client: $client);
        $connector->saveObjects($this->createIndexOptionsStub(), $objects);

        $connector->waitLastTask(self::STORE_ID);
    }

    // ── castProductObject() ──

    public function testCastProductObjectConvertsNumericStringToInteger(): void
    {
        $connector = $this->createObjectToTest();

        $data = ['qty' => '5'];
        $connector->castProductObject($data);

        $this->assertSame(5, $data['qty']);
    }

    public function testCastProductObjectLeavesNonCastableAttributesUntouched(): void
    {
        $connector = $this->createObjectToTest();

        $data = ['sku' => '12345', 'name' => '100 Faces'];
        $connector->castProductObject($data);

        $this->assertSame('12345', $data['sku']);
        $this->assertSame('100 Faces', $data['name']);
    }

    public function testCastProductObjectSplitsPipeSeparatedStringsIntoTypedArray(): void
    {
        $connector = $this->createObjectToTest();

        $data = ['color_ids' => '1|2|3'];
        $connector->castProductObject($data);

        $this->assertSame([1, 2, 3], $data['color_ids']);
    }

    // ── isValidFloat() ──

    public function testIsValidFloatReturnsFalseForValuesThatEvaluateToInfinity(): void
    {
        $connector = $this->createObjectToTest();

        $this->assertFalse($connector->isValidFloat('1.8e308'));
    }

    public function testIsValidFloatReturnsTrueForRegularFloatingPointValues(): void
    {
        $connector = $this->createObjectToTest();

        $this->assertTrue($connector->isValidFloat('3.14'));
    }

    // ── getLastTaskId() ──

    public function testGetLastTaskIdReturnsNullWhenNoOperationHasBeenPerformed(): void
    {
        $connector = $this->createObjectToTest();

        $this->assertNull($connector->getLastTaskId());
    }

    public function testGetLastTaskIdReturnsStoreSpecificTaskIdAfterOperation(): void
    {
        $objects = [['objectID' => '1', 'name' => 'A']];

        $strategy = $this->createMock(SendStrategyInterface::class);
        $strategy->expects($this->once())
            ->method('send')
            ->willReturn([AlgoliaConnector::ALGOLIA_API_TASK_ID => self::TASK_ID]);

        $connector = $this->createObjectToTest(strategy: $strategy);

        $connector->saveObjects($this->createIndexOptionsStub(), $objects);

        $this->assertSame(self::TASK_ID, $connector->getLastTaskId(self::STORE_ID));
    }
}
