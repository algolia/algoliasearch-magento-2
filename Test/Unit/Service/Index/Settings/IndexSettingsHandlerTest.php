<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Index\Settings;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Logger\AlgoliaLogger;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Index\Settings\IndexSettingsComparator;
use Algolia\AlgoliaSearch\Service\Index\Settings\IndexSettingsHandler;
use Algolia\AlgoliaSearch\Service\Index\Settings\IndexSettingsPreserver;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;

#[AllowMockObjectsWithoutExpectations]
class IndexSettingsHandlerTest extends TestCase
{
    protected ?AlgoliaConnector $connector = null;

    protected ?ConfigHelper $config = null;
    protected ?IndexSettingsComparator $indexSettingsComparator = null;
    protected ?IndexSettingsPreserver $indexSettingsPreserver = null;
    protected ?AlgoliaLogger $logger = null;

    protected ?IndexOptionsInterface $indexOptions = null;

    private ?IndexSettingsHandler $handler = null;

    protected function setUp(): void
    {
        $this->connector = $this->createMock(AlgoliaConnector::class);
        $this->config = $this->createMock(ConfigHelper::class);
        $this->indexSettingsComparator = $this->createMock(IndexSettingsComparator::class);
        $this->indexSettingsComparator->method('matches')->willReturn(false);
        $this->indexSettingsPreserver = $this->createMock(IndexSettingsPreserver::class);
        // By default preservation is a pass-through so existing expectations operate on the original payload
        $this->indexSettingsPreserver->method('preserve')->willReturnArgument(0);
        $this->logger = $this->createMock(AlgoliaLogger::class);
        $this->indexOptions = $this->createMock(IndexOptionsInterface::class);

        $this->handler = new IndexSettingsHandler(
            $this->connector,
            $this->config,
            $this->indexSettingsComparator,
            $this->indexSettingsPreserver,
            $this->logger,
        );
    }

    public function testSetSettingsWithForwardingEnabledAndMixedSettings(): void
    {
        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name', 'price'],
        ];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->with($storeId)
            ->willReturn(true);

        $invocationCount = 0;
        $this->connector->expects($this->exactly(2))
            ->method('setSettings')
            ->willReturnCallback(
                function($indexOptions, $indexSettings, $forwardToReplicas, $mergeSettings, $mergeFrom = '') use (&$invocationCount) {
                    $invocationCount++;

                    switch ($invocationCount) {
                        case 1:
                            $this->assertEquals(['attributesToRetrieve' => ['name', 'price']], $indexSettings);
                            $this->assertTrue($forwardToReplicas);
                            $this->assertFalse($mergeSettings);

                            break;
                        case 2:
                            $this->assertEquals(['customRanking' => ['desc(price)']], $indexSettings);
                            $this->assertFalse($forwardToReplicas);
                            $this->assertFalse($mergeSettings);

                            break;
                }
            }
            );

        // IndexSettingsComparator::matches() is called 3 times:
        // - once for the full payload
        // - once for $forward
        // - once for $noforward
        $this->indexSettingsComparator->expects($this->exactly(3))->method('matches');
        // Only two taskIDs are collected ($forward and $noforward
        $this->connector->expects($this->exactly(2))->method('collectTaskIdToWaitFor');

        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
    }

    public function testSetSettingsWithForwardingEnabledOnlyExcludedSettings(): void
    {
        $storeId = 1;
        $settings = [
            'ranking' => ['asc(name)'],
            'customRanking' => ['desc(price)'],
        ];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(true);

        // Only one call expected (no forwarded settings since they are sorts)
        $this->connector->expects($this->once())
            ->method('setSettings')
            ->with(
                $this->indexOptions,
                $settings,
                false
            );

        // IndexSettingsComparator::matches() is called 2 times:
        // - once for the full payload
        // - once for $noforward
        $this->indexSettingsComparator->expects($this->exactly(2))->method('matches');
        // Only one taskID is collected ($noforward)
        $this->connector->expects($this->once())->method('collectTaskIdToWaitFor');

        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
    }

    public function testSetSettingsWithForwardingEnabledOnlyForwardableSettings(): void
    {
        $storeId = 1;
        $settings = [
            'attributesToHighlight' => ['title'],
            'attributesToRetrieve' => ['name'],
        ];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(true);

        // Only one call expected (all forwarded - no excluded settings)
        $this->connector->expects($this->once())
            ->method('setSettings')
            ->with(
                $this->indexOptions,
                $settings,
                true
            );

        // IndexSettingsComparator::matches() is called 2 times:
        // - once for the full payload
        // - once for $forward
        $this->indexSettingsComparator->expects($this->exactly(2))->method('matches');
        // Only one taskID is collected ($forward)
        $this->connector->expects($this->exactly(1))->method('collectTaskIdToWaitFor');

        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
    }

    public function testSetSettingsWithForwardingDisabled(): void
    {
        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name', 'price'],
        ];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(false);

        $this->connector->expects($this->once())
            ->method('setSettings')
            ->with(
                $this->indexOptions,
                $settings,
                false
            );

        // IndexSettingsComparator::matches() is called once (since we don't split)
        $this->indexSettingsComparator->expects($this->once())->method('matches');
        // Only one taskID is collected (full payload with early return)
        $this->connector->expects($this->once())->method('collectTaskIdToWaitFor');

        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
    }

    public function testForwardSettingsWithEmptyInput(): void
    {
        $storeId = 1;
        $settings = [];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);
        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(true);

        // Connector should not be called
        $this->connector->expects($this->never())->method('setSettings');

        // IndexSettingsComparator::matches() is called once (empty array will always match and early return)
        $this->indexSettingsComparator->expects($this->once())->method('matches');
        $this->connector->expects($this->never())->method('collectTaskIdToWaitFor');

        $this->assertTrue($this->handler->setSettings($this->indexOptions, $settings));
    }


    public function testSplitSettings(): void
    {
        $settings = [
            'customRanking' => ['desc(price)'],
            'ranking' => ['asc(name)'],
            'attributesToRetrieve' => ['name'],
        ];

        [$forward, $noForward] = $this->invokeMethod($this->handler, 'splitSettings', [$settings]);

        $this->assertEquals(['attributesToRetrieve' => ['name']], $forward);
        $this->assertEquals([
            'customRanking' => ['desc(price)'],
            'ranking' => ['asc(name)'],
        ], $noForward);
    }

    public function testDifferentStoreIdsDontInterfere(): void
    {
        $storeId1 = 1;
        $storeId2 = 2;
        $settings = ['attributesToRetrieve' => ['name']];

        $indexOptions1 = $this->createMock(IndexOptionsInterface::class);
        $indexOptions1->method('getStoreId')->willReturn($storeId1);

        $indexOptions2 = $this->createMock(IndexOptionsInterface::class);
        $indexOptions2->method('getStoreId')->willReturn($storeId2);

        $this->config->method('shouldForwardPrimaryIndexSettingsToReplicas')
            ->willReturn(false);

        // Both calls should succeed as they use different store IDs
        $this->connector->expects($this->exactly(2))
            ->method('setSettings');

        // IndexSettingsComparator::matches() is called 2 times:
        // - once for the full payload of the 2 stores
        $this->indexSettingsComparator->expects($this->exactly(2))->method('matches');

        $this->connector->expects($this->exactly(2))->method('collectTaskIdToWaitFor');

        $this->assertTrue($this->handler->setSettings($indexOptions1, $settings));
        $this->assertTrue($this->handler->setSettings($indexOptions2, $settings));
    }

    public function testSkippedSetSettings(): void
    {
        $indexSettingsComparator = $this->createMock(IndexSettingsComparator::class);
        $indexSettingsComparator->method('matches')->willReturn(true);

        $this->handler = new IndexSettingsHandler(
            $this->connector,
            $this->config,
            $indexSettingsComparator,
            $this->indexSettingsPreserver,
            $this->logger,
        );

        $storeId = 1;
        $settings = [
            'customRanking' => ['desc(price)'],
            'attributesToRetrieve' => ['name'],
        ];

        $this->indexOptions->method('getStoreId')->willReturn($storeId);

        // IndexSettingsComparator::matches() is called once (match = early return)
        $indexSettingsComparator->expects($this->once())->method('matches');

        $this->connector->expects($this->never())->method('collectTaskIdToWaitFor');

        $this->assertFalse($this->handler->setSettings($this->indexOptions, $settings));
    }

    public function testGetSettingsCalledExactlyOncePerSetSettings(): void
    {
        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())
            ->method('getSettings')
            ->willReturn(['attributesForFaceting' => ['categories']]);

        $preserver = $this->createMock(IndexSettingsPreserver::class);
        $preserver->method('preserve')->willReturnArgument(0);

        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->method('matches')->willReturn(false);

        $config = $this->createMock(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn(false);

        $handler = new IndexSettingsHandler($connector, $config, $comparator, $preserver, $this->logger);
        $this->indexOptions->method('getStoreId')->willReturn(1);

        $this->assertTrue($handler->setSettings($this->indexOptions, ['attributesForFaceting' => ['categories']]));
    }

    public function testPreserverInvokedBeforeComparator(): void
    {
        $order = [];

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->method('getSettings')->willReturn(['attributesForFaceting' => ['categories']]);

        $preserver = $this->createMock(IndexSettingsPreserver::class);
        $preserver->expects($this->once())
            ->method('preserve')
            ->willReturnCallback(function ($proposed) use (&$order) {
                $order[] = 'preserve';

                return $proposed;
            });

        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->expects($this->once())
            ->method('matches')
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'matches';

                return true;
            });

        $config = $this->createMock(ConfigHelper::class);
        $config->method('isLoggingEnabled')->willReturn(false);

        $handler = new IndexSettingsHandler($connector, $config, $comparator, $preserver, $this->logger);
        $this->indexOptions->method('getStoreId')->willReturn(1);

        $handler->setSettings($this->indexOptions, ['attributesForFaceting' => ['categories']]);

        $this->assertSame(['preserve', 'matches'], $order);
    }

    public function testComparatorReceivesPreFetchedRemote(): void
    {
        $remote = ['attributesForFaceting' => ['categories', '_collections']];

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->expects($this->once())->method('getSettings')->willReturn($remote);

        $preserver = $this->createMock(IndexSettingsPreserver::class);
        $preserver->method('preserve')->willReturnArgument(0);

        $comparator = $this->createMock(IndexSettingsComparator::class);
        $comparator->expects($this->once())
            ->method('matches')
            ->with($this->indexOptions, $this->anything(), $remote)
            ->willReturn(true);

        $config = $this->createMock(ConfigHelper::class);
        $config->method('isLoggingEnabled')->willReturn(false);

        $handler = new IndexSettingsHandler($connector, $config, $comparator, $preserver, $this->logger);
        $this->indexOptions->method('getStoreId')->willReturn(1);

        $this->assertFalse($handler->setSettings($this->indexOptions, ['attributesForFaceting' => ['categories']]));
    }

    public function testNoOpDetectionAccountsForPreservedEntries(): void
    {
        $proposed = ['attributesForFaceting' => ['a', 'b']];
        $remote = ['attributesForFaceting' => ['a', 'b', '_x']];

        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->method('getSettings')->willReturn($remote);
        // The only diff was the preserved entry, so no write should be issued.
        $connector->expects($this->never())->method('setSettings');

        $preserver = $this->createMock(IndexSettingsPreserver::class);
        $preserver->method('preserve')->willReturn(['attributesForFaceting' => ['a', 'b', '_x']]);

        // Use the real comparator so the no-op detection is genuinely exercised.
        $comparator = new IndexSettingsComparator($connector);

        $config = $this->createMock(ConfigHelper::class);
        $config->method('isLoggingEnabled')->willReturn(false);

        $handler = new IndexSettingsHandler($connector, $config, $comparator, $preserver, $this->logger);
        $this->indexOptions->method('getStoreId')->willReturn(1);

        $this->assertFalse($handler->setSettings($this->indexOptions, $proposed));
    }

    #[DataProvider('settingsProvider')]
    public function testForwardAndNoForwardSettingsChanges(
        array $proposed,
        array $remote,
        bool $forwardToReplicas,
        int $expectedNumberOfTasksCollected
    ) : void
    {
        $connector = $this->createMock(AlgoliaConnector::class);
        $connector->method('getSettings')->willReturn($remote);

        $preserver = $this->createMock(IndexSettingsPreserver::class);
        $preserver->method('preserve')->willReturn($proposed);

        $comparator = new IndexSettingsComparator($connector);

        $config = $this->createMock(ConfigHelper::class);
        $config->method('shouldForwardPrimaryIndexSettingsToReplicas')->willReturn($forwardToReplicas);

        $handler = new IndexSettingsHandler($connector, $config, $comparator, $preserver, $this->logger);
        $this->indexOptions->method('getStoreId')->willReturn(1);

        $connector->expects($this->exactly($expectedNumberOfTasksCollected))->method('setSettings');
        $connector->expects($this->exactly($expectedNumberOfTasksCollected))->method('collectTaskIdToWaitFor');

        $handler->setSettings($this->indexOptions, $proposed);
    }

    public static function settingsProvider(): array
    {
        return [
            [ // Both forward and noforward have changes => 2 collected tasks expected
                'proposed' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'remote' => [
                    'customRanking' => ['asc(price)'],
                    'attributesToRetrieve' => ['title']
                ],
                'forwardToReplicas' => true,
                'expectedNumberOfTasksCollected' => 2
            ],
            [ // Only forward has changes  => 1 collected task expected
                'proposed' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'remote' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['title']
                ],
                'forwardToReplicas' => true,
                'expectedNumberOfTasksCollected' => 1
            ],
            [ // Only noforward has changes => 1 collected task expected
                'proposed' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'remote' => [
                    'customRanking' => ['asc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'forwardToReplicas' => true,
                'expectedNumberOfTasksCollected' => 1
            ],
            [ // forward and noforward are identical => 0 collected task expected
                'proposed' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'remote' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'forwardToReplicas' => true,
                'expectedNumberOfTasksCollected' => 0
            ],
            [ // Both forward and noforward have changes but forward to replicas is set to false => 1 collected task expected
                'proposed' => [
                    'customRanking' => ['desc(price)'],
                    'attributesToRetrieve' => ['name']
                ],
                'remote' => [
                    'customRanking' => ['asc(price)'],
                    'attributesToRetrieve' => ['title']
                ],
                'forwardToReplicas' => false,
                'expectedNumberOfTasksCollected' => 1
            ],
        ];
    }
}
